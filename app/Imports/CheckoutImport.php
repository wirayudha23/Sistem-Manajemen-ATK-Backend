<?php

namespace App\Imports;

use App\Models\Checkout;
use App\Models\CheckoutDetail;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CheckoutImport implements ToCollection, WithHeadingRow
{
    // Variabel untuk mengumpulkan error per baris
    public $errors = [];

    public function headingRow(): int
    {
        return 3; // Mengambil header dari baris ke-3
    }

    /**
     * Method collection akan dipanggil dengan koleksi baris dari file Excel.
     * Gunakan WithHeadingRow agar header diambil dari baris ke-3.
     * Karena file dimulai dari kolom B, pastikan header di Excel sudah tepat.
     */
    public function collection(Collection $rows)
    {
        $dataGroups = [];
        $currentRowNumber = 4; // Data mulai dari baris 4 (baris 3 adalah header)

        foreach ($rows as $row) {
            // dd($row);
            // Ambil nilai dari kolom, pastikan trim untuk menghindari spasi ekstra.
            $tanggal     = trim($row['tanggal'] ?? '');
            $namaBarang  = trim($row['nama_barang'] ?? '');
            $jumlah      = trim($row['jumlah'] ?? '');
            $inisial     = trim($row['inisial'] ?? '');

            // Validasi format tanggal (harus dd-mm-yyyy)
            try {
                if (is_numeric($tanggal)) {
                    // Jika nilai tanggal berupa angka, konversi menggunakan excelToDateTimeObject
                    $tanggalObj = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($tanggal));
                } else if (strpos($tanggal, '-') !== false) {
                    $tanggalObj = Carbon::createFromFormat('d-m-Y', $tanggal);
                } else if (strpos($tanggal, '/') !== false) {
                    $tanggalObj = Carbon::createFromFormat('d/m/Y', $tanggal);
                } else {
                    // Jika tidak ada pemisah yang dikenal, lempar exception
                    throw new \Exception("Format tanggal tidak dikenal.");
                }
            } catch (\Exception $e) {
                $this->errors[] = "Baris {$currentRowNumber}: Format tanggal '{$tanggal}' tidak valid. Diharapkan dd/mm/yyyy atau dd-mm-yyyy.";
                $currentRowNumber++;
                continue;
            }

            // Validasi jumlah harus angka
            if (!is_numeric($jumlah)) {
                $this->errors[] = "Baris {$currentRowNumber}: Jumlah '{$jumlah}' harus berupa angka.";
            }

            // Validasi Nama Barang (case sensitive)
            $product = Product::where('name', $namaBarang)->first();
            if (!$product) {
                $this->errors[] = "Baris {$currentRowNumber}: Nama Barang '{$namaBarang}' tidak terdaftar.";
            }

            // Validasi Inisial: harus ada di tabel users dengan role dosen
            $user = User::where('initial', $inisial)->where('role', 'dosen')->first();
            if (!$user) {
                $this->errors[] = "Baris {$currentRowNumber}: Inisial '{$inisial}' tidak valid atau bukan dosen.";
            }

            if ($product && is_numeric($jumlah)) {
                if ($product->stock < $jumlah) {
                    $this->errors[] = "Baris {$currentRowNumber}: Jumlah '{$jumlah}' melebihi stok barang '{$namaBarang}'. Stok saat ini: {$product->stock}.";
                }
            }

            // Jika ada error pada baris ini, lanjutkan ke baris berikutnya
            if (!empty($this->errors)) {
                $currentRowNumber++;
                continue;
            }

            // Group data berdasarkan kombinasi Tanggal dan Inisial
            $groupKey = $tanggal . '|' . $inisial;
            $dataGroups[$groupKey][] = [
                'tanggal'           => $tanggalObj, // Carbon instance
                'product_id'        => $product->id,
                'checkout_quantity' => $jumlah,
                'user_id'           => $user->id,
                'row_number'        => $currentRowNumber, // opsional untuk referensi
                // 'nama_barang'       => $namaBarang,
            ];
            $currentRowNumber++;
        }

        // Jika terdapat error, batalkan proses dengan melempar exception yang berisi seluruh error
        if (!empty($this->errors)) {
            throw new \Exception(implode("\n", $this->errors));
        }

        // Jika tidak ada error, lakukan penyimpanan data dalam satu transaksi
        DB::transaction(function () use ($dataGroups) {
            foreach ($dataGroups as $groupKey => $items) {
                // Ambil data pertama sebagai acuan untuk checkout (tanggal dan user_id)
                $first = $items[0];
                $checkout = Checkout::create([
                    'user_id'       => $first['user_id'],
                    'checkout_date' => $first['tanggal']->format('Y-m-d'),
                ]);

                foreach ($items as $item) {
                    // Update stok produk
                    Product::where('id', $item['product_id'])
                        ->decrement('stock', $item['checkout_quantity']);
                }
                // Simpan tiap detail checkout untuk grup ini
                foreach ($items as $item) {
                    CheckoutDetail::create([
                        'checkout_id'       => $checkout->id,
                        'product_id'        => $item['product_id'],
                        'checkout_quantity' => $item['checkout_quantity'],
                    ]);
                }
            }
        });
    }
}
