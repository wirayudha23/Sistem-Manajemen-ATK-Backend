<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\StafApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\StudyProgram;

class PreloadPegawaiData extends Command
{
    protected $signature = 'pegawai:preload
                            {--batch-size=25 : Jumlah item per batch (default 25)}
                            {--verify        : Verifikasi cache setelah selesai}
                            {--max-retries=3 : Maksimal retry untuk API call yang gagal}
                            {--delay=200     : Delay dalam ms antara API calls}
                            {--save-db       : Simpan data ke database}
                            {--update-existing : Update data yang sudah ada di database}';

    protected $description = 'Preload data pegawai dengan strategi page = total data yang sudah dimuat, dan simpan ke database.';

    public function handle()
    {
        $batchSize = (int) $this->option('batch-size');
        $verify = $this->option('verify');
        $maxRetries = (int) $this->option('max-retries');
        $delay = (int) $this->option('delay');
        $saveToDb = $this->option('save-db');
        $updateExisting = $this->option('update-existing');

        $service = new StafApiService();

        $totalLoadedData = 0; // Total data yang berhasil dimuat
        $totalApiCalls = 0;
        $totalCachedInisials = 0;
        $totalSavedToDb = 0;
        $totalUpdatedInDb = 0;
        $batches = [];
        $batchIndex = 0;
        $consecutiveErrors = 0;
        $maxConsecutiveErrors = 5;

        $this->info("🚀 Mulai preload dengan strategi: page = total data yang sudah dimuat");
        $this->info("   • Batch size: {$batchSize}");
        $this->info("   • Max retries: {$maxRetries}");
        $this->info("   • Delay: {$delay}ms");
        $this->info("   • Save to DB: " . ($saveToDb ? 'Yes' : 'No'));
        $this->info("   • Update existing: " . ($updateExisting ? 'Yes' : 'No'));
        $this->line("");

        while (true) {
            $currentPage = $totalLoadedData; // Page = total data yang sudah dimuat
            $batchIndex++;

            $this->info("📦 Batch #{$batchIndex}: fetching page {$currentPage}");

            // Fetch data dengan retry mechanism
            $items = $this->fetchWithRetry($service, $currentPage, $maxRetries);
            $totalApiCalls++;

            if ($items === null) {
                $consecutiveErrors++;
                $this->error("   ❌ Gagal fetch page {$currentPage} setelah {$maxRetries} percobaan");

                if ($consecutiveErrors >= $maxConsecutiveErrors) {
                    $this->error("⚠️ Terlalu banyak error berturut-turut ({$consecutiveErrors}), hentikan preload");
                    break;
                }

                // Skip ke page berikutnya dengan asumsi batch size
                $totalLoadedData += $batchSize;
                continue;
            }

            $itemCount = count($items);
            $consecutiveErrors = 0; // Reset counter jika berhasil

            // Process items: Cache dan Database
            $batchResult = $this->processItems($items, $saveToDb, $updateExisting);

            $batches[] = [
                'page' => $currentPage,
                'items' => $itemCount,
                'cached' => $batchResult['cached'],
                'saved' => $batchResult['saved'],
                'updated' => $batchResult['updated']
            ];

            $totalLoadedData += $itemCount; // Update total data yang dimuat
            $totalCachedInisials += $batchResult['cached'];
            $totalSavedToDb += $batchResult['saved'];
            $totalUpdatedInDb += $batchResult['updated'];

            $this->info("   ✅ Items: {$itemCount} | Cached: {$batchResult['cached']}");
            if ($saveToDb) {
                $this->info("   💾 DB Saved: {$batchResult['saved']} | Updated: {$batchResult['updated']}");
            }
            $this->info("   📈 Total data dimuat: {$totalLoadedData}");

            // Kondisi berhenti
            if ($itemCount == 0) {
                $this->warn("⚠️ Page {$currentPage} kosong, hentikan preload");
                break;
            }

            if ($itemCount < $batchSize) {
                $this->warn("⚠️ Hanya {$itemCount} item (< {$batchSize}), kemungkinan sudah mencapai akhir data");
                break;
            }

            // Safety limit
            if ($totalLoadedData > 50000 || $batchIndex > 2000) {
                $this->warn("⚠️ Mencapai safety limit, hentikan preload");
                break;
            }

            // Delay untuk menghindari rate limiting
            if ($delay > 0) {
                usleep($delay * 1000); // Convert ms to microseconds
            }
        }

        // Ringkasan akhir
        $this->displaySummary($batchIndex, $batches, $totalLoadedData, $totalCachedInisials, $totalApiCalls, $totalSavedToDb, $totalUpdatedInDb, $saveToDb);

        // Verifikasi jika diminta
        if ($verify) {
            $this->verifyCache();
        }

        return Command::SUCCESS;
    }

    /**
     * Process items: Cache dan simpan ke database
     */
    private function processItems(array $items, bool $saveToDb, bool $updateExisting): array
    {
        $cachedCount = 0;
        $savedCount = 0;
        $updatedCount = 0;
        $uniqueInisials = [];
        $dbBatch = [];

        foreach ($items as $pegawai) {
            $inisial = strtoupper(trim($pegawai['inisial'] ?? ''));
            $nip = trim($pegawai['nip'] ?? '');

            // Skip jika data tidak lengkap
            if (!$inisial || !$nip) {
                continue;
            }

            // Cache unique inisials
            if (!isset($uniqueInisials[$inisial])) {
                $uniqueInisials[$inisial] = true;
                $cacheKey = "pegawai_inisial_{$inisial}";
                Cache::put($cacheKey, $pegawai, 3600); // Cache 1 jam
                $cachedCount++;
            }

            // Prepare untuk database
            if ($saveToDb) {
                $userData = $this->mapApiDataToUser($pegawai);
                if ($userData) {
                    $dbBatch[] = $userData;
                }
            }
        }

        // Simpan ke database dalam batch
        if ($saveToDb && !empty($dbBatch)) {
            $dbResult = $this->saveBatchToDatabase($dbBatch, $updateExisting);
            $savedCount = $dbResult['saved'];
            $updatedCount = $dbResult['updated'];
        }

        return [
            'cached' => $cachedCount,
            'saved' => $savedCount,
            'updated' => $updatedCount
        ];
    }

    /**
     * Map data API ke struktur User model
     */
    private function mapApiDataToUser(array $pegawai): ?array
    {
        try {
            // Validasi field wajib
            if (empty($pegawai['nip']) || empty($pegawai['inisial'])) {
                return null;
            }

            $posisi = trim($pegawai['posisi'] ?? '');
            if ($posisi === 'Staf Adm. Akademik dan Kemahasiswaan') {
                $role = 'BAAK';
                $position = 'Tendik';
            } else {
                $role = 'Staff';
                $position = $posisi;
            }

            return [
                'name' => trim($pegawai['nama'] ?? ''),
                'nip' => trim($pegawai['nip']),
                'initial' => strtoupper(trim($pegawai['inisial'])),
                'email' => trim($pegawai['email'] ?? ''),
                'position' => $position,
                'role' => $role,
                'homebase' => trim($pegawai['homebase'] ?? ''),
                'updated_at' => now(),
                'created_at' => now()
            ];
        } catch (\Exception $e) {
            Log::warning("Error mapping API data: " . $e->getMessage(), ['pegawai' => $pegawai]);
            return null;
        }
    }

    /**
     * Simpan batch data ke database dengan upsert
     */
    private function saveBatchToDatabase(array $dataBatch, bool $updateExisting): array
    {
        $savedCount = 0;
        $updatedCount = 0;

        // 1) Siapkan study_program_id dari homebase, lalu buang key homebase
        foreach ($dataBatch as &$userData) {
            $homebaseName = trim($userData['homebase'] ?? '');

            if ($homebaseName !== '') {
                // Cari atau buat StudyProgram sesuai nama homebase
                $sp = StudyProgram::firstOrCreate(
                    ['name' => $homebaseName],
                    ['id' => (string) \Illuminate\Support\Str::uuid()]
                );
                $userData['study_program_id'] = $sp->id;
            } else {
                $userData['study_program_id'] = null;
            }

            // Hapus key homebase agar tidak mass-assign error
            unset($userData['homebase']);
        }
        unset($userData); // break reference

        try {
            if ($updateExisting) {
                // Upsert manual: update jika ada, buat baru jika tidak
                foreach ($dataBatch as $userData) {
                    $existing = User::where('nip', $userData['nip'])->first();
                    if ($existing) {
                        $existing->update($userData);
                        $updatedCount++;
                    } else {
                        User::create($userData);
                        $savedCount++;
                    }
                }
            } else {
                // Hanya insert data baru
                foreach ($dataBatch as $userData) {
                    if (!User::where('nip', $userData['nip'])->exists()) {
                        User::create($userData);
                        $savedCount++;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Error saving batch to database: " . $e->getMessage());
            // Fallback: coba satu-per-satu dengan updateOrCreate
            foreach ($dataBatch as $userData) {
                try {
                    if ($updateExisting) {
                        $user = User::updateOrCreate(
                            ['nip' => $userData['nip']],
                            $userData
                        );
                        if ($user->wasRecentlyCreated) {
                            $savedCount++;
                        } else {
                            $updatedCount++;
                        }
                    } else {
                        if (!User::where('nip', $userData['nip'])->exists()) {
                            User::create($userData);
                            $savedCount++;
                        }
                    }
                } catch (\Exception $inner) {
                    Log::warning("Error saving individual user: " . $inner->getMessage(), [
                        'nip' => $userData['nip'],
                    ]);
                }
            }
        }

        return [
            'saved' => $savedCount,
            'updated' => $updatedCount,
        ];
    }
    private function fetchWithRetry(StafApiService $service, int $page, int $maxRetries): ?array
    {
        $attempt = 1;

        while ($attempt <= $maxRetries) {
            try {
                $items = $service->fetchPage($page);

                if (is_array($items)) {
                    if ($attempt > 1) {
                        $this->info("   ✅ Berhasil pada percobaan ke-{$attempt}");
                    }
                    return $items;
                }

            } catch (\Exception $e) {
                $this->warn("   ⚠️ Percobaan {$attempt}/{$maxRetries} gagal: " . $e->getMessage());
                Log::warning("API retry attempt {$attempt} for page {$page}: " . $e->getMessage());
            }

            if ($attempt < $maxRetries) {
                $waitTime = $attempt * 2; // Exponential backoff: 2s, 4s, 6s...
                $this->info("   ⏳ Menunggu {$waitTime} detik sebelum retry...");
                sleep($waitTime);
            }

            $attempt++;
        }

        return null;
    }

    /**
     * Tampilkan ringkasan hasil preload
     */
    private function displaySummary(int $batchIndex, array $batches, int $totalLoadedData, int $totalCachedInisials, int $totalApiCalls, int $totalSavedToDb = 0, int $totalUpdatedInDb = 0, bool $saveToDb = false): void
    {
        $this->line("");
        $this->info("🏁 RINGKASAN PRELOAD:");
        $this->info("   • Total batch processed : {$batchIndex}");
        $this->info("   • Total data dimuat     : {$totalLoadedData}");
        $this->info("   • Total inisial cached  : {$totalCachedInisials}");
        $this->info("   • Total API calls       : {$totalApiCalls}");

        if ($saveToDb) {
            $this->info("   • Total saved to DB     : {$totalSavedToDb}");
            $this->info("   • Total updated in DB   : {$totalUpdatedInDb}");
            $this->info("   • Total DB operations   : " . ($totalSavedToDb + $totalUpdatedInDb));
        }

        if (!empty($batches)) {
            $avgItemsPerBatch = round($totalLoadedData / count($batches), 1);
            $avgCachedPerBatch = round($totalCachedInisials / count($batches), 1);
            $this->info("   • Rata-rata items/batch : {$avgItemsPerBatch}");
            $this->info("   • Rata-rata cached/batch: {$avgCachedPerBatch}");

            if ($saveToDb && ($totalSavedToDb + $totalUpdatedInDb) > 0) {
                $avgDbPerBatch = round(($totalSavedToDb + $totalUpdatedInDb) / count($batches), 1);
                $this->info("   • Rata-rata DB ops/batch: {$avgDbPerBatch}");
            }
        }

        $this->line("");
        $this->info("📊 DETAIL BATCH:");

        $headers = ['Batch', 'Page', 'Items', 'Cached', 'Total Data'];
        if ($saveToDb) {
            $headers = array_merge($headers, ['Saved', 'Updated']);
        }

        $tableData = collect($batches)->map(function ($batch, $index) use ($saveToDb) {
            static $runningTotal = 0;
            $runningTotal += $batch['items'];

            $row = [
                $index + 1,
                $batch['page'],
                $batch['items'],
                $batch['cached'],
                $runningTotal
            ];

            if ($saveToDb) {
                $row[] = $batch['saved'] ?? 0;
                $row[] = $batch['updated'] ?? 0;
            }

            return $row;
        })->toArray();

        $this->table($headers, $tableData);

        // Database summary jika enabled
        if ($saveToDb && ($totalSavedToDb + $totalUpdatedInDb) > 0) {
            $this->line("");
            $this->info("💾 DATABASE SUMMARY:");
            $this->info("   • New records created: {$totalSavedToDb}");
            $this->info("   • Existing records updated: {$totalUpdatedInDb}");
            $this->info("   • Success rate: " . round((($totalSavedToDb + $totalUpdatedInDb) / $totalLoadedData) * 100, 1) . "%");
        }
    }

    /**
     * Verifikasi cache setelah preload
     */
    private function verifyCache(): void
    {
        $this->line("");
        $this->info("🔍 Verifikasi cache...");

        // Test beberapa inisial random dari cache
        $testInisials = ['AA', 'AB', 'AC', 'AD', 'AE']; // Contoh inisial untuk test
        $foundCount = 0;

        foreach ($testInisials as $inisial) {
            $cacheKey = "pegawai_inisial_{$inisial}";
            if (Cache::has($cacheKey)) {
                $data = Cache::get($cacheKey);
                $this->info("   ✅ {$inisial}: " . ($data['nama'] ?? 'N/A'));
                $foundCount++;
            } else {
                $this->warn("   ❌ {$inisial}: tidak ditemukan di cache");
            }
        }

        $this->info("Cache verification: {$foundCount}/{" . count($testInisials) . "} test inisials found");
    }
}
