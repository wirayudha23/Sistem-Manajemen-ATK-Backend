<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;

class ProductImport implements ToCollection, WithHeadingRow
{
    /** @var array Menampung semua pesan error per‐baris */
    public array $errors = [];

    /** @var array Data produk yang sudah lolos validasi (untuk di‐insert) */
    protected array $dataToInsert = [];

    public function collection(Collection $rows)
    {
        // Untuk mencegah duplikat nama produk di dalam sheet (case‐insensitive)
        $sheetNamesLower = [];

        foreach ($rows as $index => $row) {
            // Baris Excel sebenarnya berada di index+2 (karena baris 1 header)
            $rowNumber = $index + 2;

            // 1. Baca dan trim kolom sesuai header:
            //    Excel header: "Nama Produk" | "Harga" | "Kategori" | "Satuan"
            //    Dikonversi oleh WithHeadingRow menjadi keys:
            //      'nama_produk', 'harga', 'kategori', 'satuan'
            $rawName         = trim($row['nama_produk'] ?? '');
            $rawPrice        = trim($row['harga']      ?? '');
            $rawCategoryName = trim($row['kategori']   ?? '');
            $rawUnitName     = trim($row['satuan']     ?? '');

            // Array untuk menampung error di baris ini
            $rowErrors = [];

            // 2. Validasi kolom wajib (kecuali stok, karena tidak di‐import)
            if ($rawName === '') {
                $rowErrors[] = "Baris {$rowNumber}: kolom 'Nama Produk' wajib diisi.";
            }
            if ($rawPrice === '') {
                $rowErrors[] = "Baris {$rowNumber}: kolom 'Harga' wajib diisi.";
            }
            if ($rawCategoryName === '') {
                $rowErrors[] = "Baris {$rowNumber}: kolom 'Kategori' wajib diisi.";
            }
            if ($rawUnitName === '') {
                $rowErrors[] = "Baris {$rowNumber}: kolom 'Satuan' wajib diisi.";
            }

            // 3. Validasi format Harga (harus integer ≥ 0)
            $price = null;
            if ($rawPrice !== '') {
                if (! is_numeric($rawPrice) || intval($rawPrice) != $rawPrice) {
                    $rowErrors[] = "Baris {$rowNumber}: Harga '{$rawPrice}' harus berupa bilangan bulat.";
                } elseif (intval($rawPrice) < 0) {
                    $rowErrors[] = "Baris {$rowNumber}: Harga '{$rawPrice}' tidak boleh kurang dari 0.";
                } else {
                    $price = intval($rawPrice);
                }
            }

            // 4. Cek duplikat Nama Produk di dalam sheet (case‐insensitive)
            if ($rawName !== '') {
                $lowerName = mb_strtolower($rawName);
                if (in_array($lowerName, $sheetNamesLower)) {
                    $rowErrors[] = "Baris {$rowNumber}: duplikat Nama Produk '{$rawName}' di sheet.";
                } else {
                    $sheetNamesLower[] = $lowerName;
                }
            }

            // 5. Cek duplikat Nama Produk di database (case‐insensitive)
            if ($rawName !== '') {
                $existsInDb = Product::whereRaw('LOWER(name) = ?', [mb_strtolower($rawName)])->exists();
                if ($existsInDb) {
                    $rowErrors[] = "Baris {$rowNumber}: Nama Produk '{$rawName}' sudah ada di database.";
                }
            }

            // 6. Validasi keberadaan Kategori di tabel categories
            $category = null;
            if ($rawCategoryName !== '') {
                $category = Category::whereRaw('LOWER(name) = ?', [mb_strtolower($rawCategoryName)])->first();
                if (! $category) {
                    $rowErrors[] = "Baris {$rowNumber}: Kategori '{$rawCategoryName}' tidak ditemukan.";
                }
            }

            // 7. Validasi keberadaan Satuan di tabel units
            $unit = null;
            if ($rawUnitName !== '') {
                $unit = Unit::whereRaw('LOWER(name) = ?', [mb_strtolower($rawUnitName)])->first();
                if (! $unit) {
                    $rowErrors[] = "Baris {$rowNumber}: Satuan '{$rawUnitName}' tidak ditemukan.";
                }
            }

            // 8. Jika ada minimal satu error di $rowErrors, catat semuanya lalu skip insert
            if (! empty($rowErrors)) {
                foreach ($rowErrors as $errorMsg) {
                    $this->errors[] = $errorMsg;
                }
                continue;
            }

            // 9. Jika tidak ada error, siapkan data untuk di‐insert
            $this->dataToInsert[] = [
                'name'        => $rawName,
                'price'       => $price,
                'category_id' => $category->id,
                'unit_id'     => $unit->id,
                // Kolom 'stock' tidak di‐insert karena di‐abaikan
            ];
        }

        // 10. Setelah memproses semua baris, jika ada error → throw Exception
        if (! empty($this->errors)) {
            throw new \Exception('Import dibatalkan.');
        }

        // 11. Jika tidak ada error, insert setiap produk yang valid
        foreach ($this->dataToInsert as $data) {
            Product::create($data);
        }
    }
}

