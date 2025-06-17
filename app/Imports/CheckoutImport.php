<?php

namespace App\Imports;

use App\Models\Checkout;
use App\Models\CheckoutDetail;
use App\Models\Product;
use App\Models\User;
use App\Models\Purpose;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use DB;
use Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class CheckoutImport implements ToCollection
{
    protected $errors = [];

    public function collection(Collection $rows)
    {
        // 1) Validasi header
        $header = $rows->first()->toArray();
        $expectedHeader = ['No', 'Tanggal', 'Nama Barang', 'Jumlah', 'Inisial', 'Kebutuhan', 'Deskripsi'];
        if ($header !== $expectedHeader) {
            throw new \Exception("Header file import tidak sesuai. Diperlukan kolom persis: " . implode(', ', $expectedHeader));
        }

        // 2) Read data rows
        $dataRows = $rows->slice(1);
        $grouped = [];

        // 3) Loop untuk validasi per baris dan grouping
        foreach ($dataRows as $index => $row) {
            $barisNomor = $index + 2;
            $record = array_combine($header, $row->toArray());
            $rowErrors = [];

            // Skip jika semua kolom selain 'No' kosong
            $values = $record;
            unset($values['No']);
            if (collect($values)->every(fn($v) => is_null($v) || trim((string)$v) === '')) {
                continue;
            }

            // a) Validasi & parse tanggal
            try {
                $raw = $record['Tanggal'];
                if (is_numeric($raw)) {
                    $dt = ExcelDate::excelToDateTimeObject($raw);
                    $parsedDate = Carbon::instance($dt)->setTimezone('Asia/Jakarta');
                } else {
                    $parsedDate = Carbon::createFromFormat('d-m-Y', $raw, 'Asia/Jakarta');
                }
                $now = Carbon::now('Asia/Jakarta');
                $parsedDate->setTime($now->hour, $now->minute, $now->second);
                if ($parsedDate->format('Y-m-d') < $now->format('Y-m-d')) {
                    $rowErrors[] = "Tanggal ('{$raw}') tidak boleh sebelum hari ini.";
                }
            } catch (\Exception $e) {
                $rowErrors[] = "Format Tanggal ('{$record['Tanggal']}') tidak valid. Harus 'd-m-Y'.";
            }

            // b) Lookup product
            $productName = trim($record['Nama Barang']);
            $product = Product::whereRaw('LOWER(name) = ?', [strtolower($productName)])->first();
            if (!$product) {
                $rowErrors[] = "Nama Barang '{$productName}' tidak ditemukan.";
            }

            // c) Validasi jumlah
            $qtyRaw = $record['Jumlah'];
            if (!is_numeric($qtyRaw) || intval($qtyRaw) < 1) {
                $rowErrors[] = "Jumlah ('{$qtyRaw}') harus bilangan bulat ≥ 1.";
            } else {
                $qty = intval($qtyRaw);
            }

            // d) Lookup user dari initial
            $initial = trim($record['Inisial']);
            $user = User::whereRaw('LOWER(initial) = ?', [strtolower($initial)])
                        ->where('role', 'Staff')
                        ->first();
            if (!$user) {
                $rowErrors[] = "Inisial '{$initial}' tidak ditemukan atau bukan Staff.";
            }

            // e) Lookup purpose
            $purposeName = trim($record['Kebutuhan']);
            $purpose = Purpose::whereRaw('LOWER(name) = ?', [strtolower($purposeName)])->first();
            if (!$purpose) {
                $rowErrors[] = "Kebutuhan '{$purposeName}' tidak ditemukan.";
            }

            // f) Validasi deskripsi
            $desc = $record['Deskripsi'];
            if ($desc !== null && strlen($desc) > 2000) {
                $rowErrors[] = "Deskripsi maksimal 2000 karakter.";
            }

            // Jika ada error → simpan & lanjut
            if (!empty($rowErrors)) {
                foreach ($rowErrors as $msg) {
                    $this->errors[] = "Baris {$barisNomor}: {$msg}";
                }
                continue;
            }

            // g) Grouping key
            $key = implode('|', [$user->id, $purpose->id, $desc ?? '', $parsedDate->toDateString()]);

            // h) Cek duplikasi dalam transaksi yang sama
            if (isset($grouped[$key])) {
                foreach ($grouped[$key]['items'] as $existing) {
                    if ($existing['product_id'] === $product->id) {
                        $this->errors[] = "Baris {$barisNomor}: Nama Barang '{$productName}' muncul lebih dari sekali dalam satu transaksi.";
                        break;
                    }
                }
                if (!empty($this->errors) && str_contains(end($this->errors), 'muncul lebih dari sekali')) {
                    continue;
                }
            }

            // i) Tambah ke grouping
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['user_id'=>$user->id, 'purpose_id'=>$purpose->id, 'description'=>$desc,
                                   'checkout_date'=>$parsedDate, 'items'=>[]];
            }
            $grouped[$key]['items'][] = ['product_id'=>$product->id,'checkout_quantity'=>$qty,'barisNomor'=>$barisNomor];
        }

        // 4) Cek stok kumulatif across all groups
        $stockMap = Product::pluck('stock', 'id')->toArray();
        foreach ($grouped as $info) {
            foreach ($info['items'] as $detail) {
                $pid = $detail['product_id'];
                $req = $detail['checkout_quantity'];
                if (!isset($stockMap[$pid]) || $stockMap[$pid] < $req) {
                    $this->errors[] = "Baris {$detail['barisNomor']}: Stok untuk '" . Product::find($pid)->name . "' tidak mencukupi. (Tersisa: {$stockMap[$pid]} , permintaan: {$req})";
                } else {
                    $stockMap[$pid] -= $req;
                }
            }
        }

        // 5) Jika ada error → batal import
        if (!empty($this->errors)) {
            throw new \Exception(json_encode($this->errors));
        }

        // 6) Simpan ke database
        DB::beginTransaction();
        try {
            foreach ($grouped as $info) {
                $checkout = Checkout::create(['user_id'=>$info['user_id'],'purpose_id'=>$info['purpose_id'],
                    'description'=>$info['description'],'checkout_date'=>$info['checkout_date']]);
                foreach ($info['items'] as $detail) {
                    CheckoutDetail::create(['checkout_id'=>$checkout->id,'product_id'=>$detail['product_id'],
                                             'checkout_quantity'=>$detail['checkout_quantity']]);
                    Product::where('id', $detail['product_id'])->decrement('stock', $detail['checkout_quantity']);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error import checkout: ' . $e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            throw new \Exception("Gagal menyimpan data. Silakan coba lagi.");
        }
    }
}


//kode masa lalu
// <?php

// namespace App\Imports;

// use App\Models\Checkout;
// use App\Models\CheckoutDetail;
// use App\Models\Product;
// use App\Models\User;
// use App\Models\Purpose;
// use Illuminate\Support\Collection;
// use Carbon\Carbon;
// use DB;
// use Log;
// use Maatwebsite\Excel\Concerns\ToCollection;
// use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

// class CheckoutImport implements ToCollection
// {
//     protected $errors = [];

//     public function collection(Collection $rows)
//     {
//         // 1) Validasi header
//         $header = $rows->first()->toArray();
//         $expectedHeader = ['No', 'Tanggal', 'Nama Barang', 'Jumlah', 'Inisial', 'Kebutuhan', 'Deskripsi'];
//         if ($header !== $expectedHeader) {
//             throw new \Exception("Header file import tidak sesuai. Diperlukan kolom persis: " . implode(', ', $expectedHeader));
//         }

//         // 2) Read data rows
//         $dataRows = $rows->slice(1);
//         $grouped = [];

//         // 3) Loop untuk validasi per baris dan grouping
//         foreach ($dataRows as $index => $row) {
//             $barisNomor = $index + 2;
//             $record = array_combine($header, $row->toArray());
//             $rowErrors = [];

//             // Skip jika semua kolom selain 'No' kosong
//             $values = $record;
//             unset($values['No']);
//             if (collect($values)->every(fn($v) => is_null($v) || trim((string)$v) === '')) {
//                 continue;
//             }

//             // a) Validasi & parse tanggal (boleh di masa lalu)
//             try {
//                 $raw = $record['Tanggal'];
//                 if (is_numeric($raw)) {
//                     $dt = ExcelDate::excelToDateTimeObject($raw);
//                     $parsedDate = Carbon::instance($dt)->setTimezone('Asia/Jakarta');
//                 } else {
//                     $parsedDate = Carbon::createFromFormat('d-m-Y', $raw, 'Asia/Jakarta');
//                 }
//                 // Tambahkan jam sesuai sekarang
//                 $now = Carbon::now('Asia/Jakarta');
//                 $parsedDate->setTime($now->hour, $now->minute, $now->second);

//             } catch (\Exception $e) {
//                 $rowErrors[] = "Format Tanggal ('{$record['Tanggal']}') tidak valid. Harus 'd-m-Y'.";
//             }

//             // b) Lookup product
//             $productName = trim($record['Nama Barang']);
//             $product = Product::whereRaw('LOWER(name) = ?', [strtolower($productName)])->first();
//             if (!$product) {
//                 $rowErrors[] = "Nama Barang '{$productName}' tidak ditemukan.";
//             }

//             // c) Validasi jumlah
//             $qtyRaw = $record['Jumlah'];
//             if (!is_numeric($qtyRaw) || intval($qtyRaw) < 1) {
//                 $rowErrors[] = "Jumlah ('{$qtyRaw}') harus bilangan bulat ≥ 1.";
//             } else {
//                 $qty = intval($qtyRaw);
//             }

//             // d) Lookup user dari initial
//             $initial = trim($record['Inisial']);
//             $user = User::whereRaw('LOWER(initial) = ?', [strtolower($initial)])
//                         ->where('role', 'Staff')
//                         ->first();
//             if (!$user) {
//                 $rowErrors[] = "Inisial '{$initial}' tidak ditemukan atau bukan Staff.";
//             }

//             // e) Lookup purpose
//             $purposeName = trim($record['Kebutuhan']);
//             $purpose = Purpose::whereRaw('LOWER(name) = ?', [strtolower($purposeName)])->first();
//             if (!$purpose) {
//                 $rowErrors[] = "Kebutuhan '{$purposeName}' tidak ditemukan.";
//             }

//             // f) Validasi deskripsi
//             $desc = $record['Deskripsi'];
//             if ($desc !== null && strlen($desc) > 2000) {
//                 $rowErrors[] = "Deskripsi maksimal 2000 karakter.";
//             }

//             // Jika ada error parsing/lookup, simpan & lanjut
//             if (!empty($rowErrors)) {
//                 foreach ($rowErrors as $msg) {
//                     $this->errors[] = "Baris {$barisNomor}: {$msg}";
//                 }
//                 continue;
//             }

//             // g) Grouping key
//             $key = implode('|', [$user->id, $purpose->id, $desc ?? '', $parsedDate->toDateString()]);

//             // h) Cek duplikasi dalam transaksi yang sama
//             if (isset($grouped[$key])) {
//                 foreach ($grouped[$key]['items'] as $existing) {
//                     if ($existing['product_id'] === $product->id) {
//                         $this->errors[] = "Baris {$barisNomor}: Nama Barang '{$productName}' muncul lebih dari sekali dalam satu transaksi.";
//                         break;
//                     }
//                 }
//                 if (!empty($this->errors) && str_contains(end($this->errors), 'muncul lebih dari sekali')) {
//                     continue;
//                 }
//             }

//             // i) Tambah ke grouping
//             if (!isset($grouped[$key])) {
//                 $grouped[$key] = [
//                     'user_id'       => $user->id,
//                     'purpose_id'    => $purpose->id,
//                     'description'   => $desc,
//                     'checkout_date' => $parsedDate,
//                     'items'         => [],
//                 ];
//             }
//             $grouped[$key]['items'][] = [
//                 'product_id'        => $product->id,
//                 'checkout_quantity' => $qty,
//                 'barisNomor'        => $barisNomor,
//             ];
//         }

//         // 4) Skip validasi stok dan tidak kurangi stok (untuk memasukkan data historis)

//         // 5) Jika ada error → batal import
//         if (!empty($this->errors)) {
//             throw new \Exception(json_encode($this->errors));
//         }

//         // 6) Simpan ke database tanpa mengurangi stok
//         DB::beginTransaction();
//         try {
//             foreach ($grouped as $info) {
//                 $checkout = Checkout::create([
//                     'user_id'       => $info['user_id'],
//                     'purpose_id'    => $info['purpose_id'],
//                     'description'   => $info['description'],
//                     'checkout_date' => $info['checkout_date'],
//                 ]);
//                 foreach ($info['items'] as $detail) {
//                     CheckoutDetail::create([
//                         'checkout_id'       => $checkout->id,
//                         'product_id'        => $detail['product_id'],
//                         'checkout_quantity' => $detail['checkout_quantity'],
//                     ]);
//                     // Tidak mengurangi stok product
//                 }
//             }
//             DB::commit();
//         } catch (\Exception $e) {
//             DB::rollBack();
//             Log::error('Error import checkout: ' . $e->getMessage(), ['trace'=>$e->getTraceAsString()]);
//             throw new \Exception("Gagal menyimpan data. Silakan coba lagi.");
//         }
//     }
// }
