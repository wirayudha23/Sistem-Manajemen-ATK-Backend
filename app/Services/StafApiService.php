<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class StafApiService
{
    private $apiUrl = 'https://v2.api.pcr.ac.id/api/pegawai';
    private $apiKey = 'Ovk9PikyPmncW649C0vzEMmRWoOz20Ng';

    /**
     * Fetch page dengan error handling yang lebih baik
     */
    public function fetchPage(int $page): array
    {
        try {
            $url = $this->apiUrl . '?' . http_build_query([
                'collection' => 'pegawai-aktif',
                'page' => $page,
            ]);

            Log::info("Fetching page {$page} from API");

            // Tingkatkan timeout dan tambahkan retry settings
            $response = Http::timeout(30) // Naikkan dari 10 ke 30 detik
                ->connectTimeout(10)      // Connection timeout
                ->retry(2, 1000)          // Retry 2x dengan delay 1 detik
                ->withHeaders([
                    'apikey' => $this->apiKey,
                    'Accept' => 'application/json',
                    'User-Agent' => 'Laravel/Pegawai-Preloader'
                ])
                ->post($url);

            if (!$response->successful()) {
                $statusCode = $response->status();
                $errorBody = $response->body();

                Log::warning("API call failed at page {$page}. Status: {$statusCode}, Body: " . substr($errorBody, 0, 200));

                // Handle specific HTTP errors
                if ($statusCode == 429) {
                    Log::warning("Rate limit hit at page {$page}, waiting...");
                    sleep(5); // Wait 5 seconds for rate limit
                    throw new \Exception("Rate limit exceeded");
                }

                if ($statusCode >= 500) {
                    throw new \Exception("Server error: {$statusCode}");
                }

                return [];
            }

            $data = $response->json();

            if (!is_array($data)) {
                Log::warning("Invalid JSON response at page {$page}");
                return [];
            }

            $items = $data['items'] ?? [];

            if (!is_array($items)) {
                Log::warning("Items is not an array at page {$page}");
                return [];
            }

            Log::info("Successfully fetched page {$page}: " . count($items) . " items");
            return $items;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("Connection error fetching page {$page}: " . $e->getMessage());
            throw new \Exception("Connection timeout or network error");

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error("Request error fetching page {$page}: " . $e->getMessage());
            throw new \Exception("HTTP request failed: " . $e->getMessage());

        } catch (\Exception $e) {
            Log::error("General error fetching page {$page}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch page dengan fallback strategy
     */
    public function fetchPageWithFallback(int $page): array
    {
        $strategies = [
            ['timeout' => 30, 'method' => 'POST'],
            ['timeout' => 45, 'method' => 'GET'],
            ['timeout' => 60, 'method' => 'POST']
        ];

        foreach ($strategies as $index => $strategy) {
            try {
                Log::info("Trying strategy " . ($index + 1) . " for page {$page}: {$strategy['method']} with {$strategy['timeout']}s timeout");

                $url = $this->apiUrl . '?' . http_build_query([
                    'collection' => 'pegawai-aktif',
                    'page' => $page,
                ]);

                $httpClient = Http::timeout($strategy['timeout'])
                    ->connectTimeout(15)
                    ->withHeaders([
                        'apikey' => $this->apiKey,
                        'Accept' => 'application/json',
                        'User-Agent' => 'Laravel/Pegawai-Preloader-Strategy-' . ($index + 1)
                    ]);

                if ($strategy['method'] === 'GET') {
                    $response = $httpClient->get($url);
                } else {
                    $response = $httpClient->post($url);
                }

                if ($response->successful()) {
                    $data = $response->json();
                    $items = $data['items'] ?? [];

                    Log::info("Strategy " . ($index + 1) . " succeeded for page {$page}: " . count($items) . " items");
                    return is_array($items) ? $items : [];
                }

            } catch (\Exception $e) {
                Log::warning("Strategy " . ($index + 1) . " failed for page {$page}: " . $e->getMessage());

                if ($index < count($strategies) - 1) {
                    sleep(2); // Wait between strategies
                }
            }
        }

        Log::error("All strategies failed for page {$page}");
        return [];
    }

    /**
     * Cari pegawai berdasarkan inisial dengan batching untuk menghindari timeout
     */
    public function findPegawaiByInisial($inisial)
    {
        try {
            // Cek cache dulu
            $cacheKey = "pegawai_inisial_" . strtoupper($inisial);
            $cachedResult = Cache::get($cacheKey);

            if ($cachedResult) {
                Log::info("Found inisial '{$inisial}' in cache");
                return $cachedResult;
            }

            // Cari dengan batch processing (setiap 25 page)
            $result = $this->searchInBatches($inisial);

            // Cache result jika ditemukan (cache 1 jam)
            if ($result) {
                Cache::put($cacheKey, $result, 3600);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Error fetching pegawai: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Pencarian dengan batch setiap 25 halaman (data unik)
     */
    private function searchInBatches($inisial, $startPage = 0)
    {
        $currentPage = $startPage;
        $totalChecked = 0;
        $batchSize = 25; // Setiap 25 halaman = data baru

        Log::info("Starting batch search for inisial: {$inisial} from page {$startPage}");

        while ($currentPage < 5000) { // Maksimal check sampai page 5000
            $batchEndPage = $currentPage + $batchSize - 1;

            Log::info("Searching batch: pages {$currentPage} to {$batchEndPage}");

            // Cek apakah batch ini masih memiliki data
            $hasData = $this->checkBatchHasData($currentPage, $batchEndPage);

            if (!$hasData) {
                Log::info("Batch starting from page {$currentPage} is empty, stopping search");
                break;
            }

            // Cari dalam batch ini (ambil sample dari beberapa page)
            $samplePages = $this->getSamplePages($currentPage, $batchEndPage);

            foreach ($samplePages as $page) {
                $result = $this->searchSinglePage($inisial, $page);

                if ($result['found']) {
                    Log::info("✅ Found '{$inisial}' on page {$page}");
                    return $result['data'];
                }

                if ($result['empty']) {
                    Log::info("Reached empty page at {$page}, stopping search");
                    return null;
                }

                $totalChecked += $result['checked_count'];

                // Jeda singkat untuk menghindari rate limiting
                usleep(200000); // 0.2 detik (naikkan dari 0.1)
            }

            Log::info("Batch completed. Total records checked so far: {$totalChecked}");
            $currentPage += $batchSize; // Lompat ke batch berikutnya

            // Break jika sudah terlalu lama (untuk menghindari timeout)
            if ((time() - LARAVEL_START) > 50) { // 50 detik
                Log::warning("Approaching timeout, stopping search at page {$currentPage}");
                break;
            }
        }

        Log::info("Search completed. Total records checked: {$totalChecked}");
        return null;
    }

    /**
     * Cek apakah batch masih memiliki data
     */
    private function checkBatchHasData($startPage, $endPage)
    {
        // Cek beberapa sample page di batch ini
        $samplePages = [$startPage, $startPage + 5, $startPage + 10];

        foreach ($samplePages as $page) {
            if ($page > $endPage)
                continue;

            $result = $this->searchSinglePage('', $page, true); // checkOnly = true
            if (!$result['empty']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dapatkan sample pages dari batch untuk dicek
     */
    private function getSamplePages($startPage, $endPage)
    {
        // Ambil beberapa sample page dari batch ini
        // Karena dalam 25 page ada data yang duplikat, kita sample saja
        return [
            $startPage,
            $startPage + 5,
            $startPage + 10,
            $startPage + 15,
            $startPage + 20
        ];
    }

    /**
     * Cari di satu halaman dengan improved timeout handling
     */
    private function searchSinglePage($inisial, $page, $checkOnly = false)
    {
        try {
            $items = $this->fetchPageWithFallback($page);

            if (empty($items)) {
                return ['found' => false, 'empty' => true, 'checked_count' => 0];
            }

            // Jika hanya check keberadaan data, return saja
            if ($checkOnly) {
                return ['found' => false, 'empty' => false, 'checked_count' => count($items)];
            }

            foreach ($items as $pegawai) {
                $apiInisial = $pegawai['inisial'] ?? '';
                if (strtoupper($apiInisial) === strtoupper($inisial)) {
                    return [
                        'found' => true,
                        'data' => $pegawai,
                        'page' => $page,
                        'checked_count' => count($items)
                    ];
                }
            }

            return [
                'found' => false,
                'empty' => false,
                'checked_count' => count($items)
            ];

        } catch (\Exception $e) {
            Log::error("Error searching page {$page}: " . $e->getMessage());
            return ['found' => false, 'empty' => false, 'checked_count' => 0];
        }
    }

    /**
     * Validasi dengan optimasi
     */
    public function validateInisial($inisial)
    {
        Log::info("🔍 Validating inisial: {$inisial}");

        $pegawai = $this->findPegawaiByInisial($inisial);

        if (!$pegawai) {
            Log::warning("❌ Inisial '{$inisial}' tidak ditemukan");
            return [
                'valid' => false,
                'message' => 'Inisial tidak ditemukan'
            ];
        }

        if ($pegawai['status_aktifitas'] !== 'Aktif') {
            Log::warning("⚠️ Pegawai dengan inisial '{$inisial}' tidak aktif. Status: " . $pegawai['status_aktifitas']);
            return [
                'valid' => false,
                'message' => 'Pegawai tidak aktif'
            ];
        }

        Log::info("✅ Inisial '{$inisial}' valid. Pegawai: " . $pegawai['nama']);
        return [
            'valid' => true,
            'data' => $pegawai
        ];
    }
}
