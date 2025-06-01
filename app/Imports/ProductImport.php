<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Unit;
use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

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

            // 1. Baca dan trim semua kolom sesuai header baru:
            //    Excel header: "Nama Produk" | "Harga" | "Stok" | "Kategori" | "Satuan"
            //    Dikonversi oleh WithHeadingRow menjadi keys:
            //      'nama_produk', 'harga', 'stok', 'kategori', 'satuan'
            $rawName         = trim($row['nama_produk'] ?? '');
            $rawPrice        = trim($row['harga']      ?? '');
            $rawStock        = trim($row['stok']       ?? '');
            $rawCategoryName = trim($row['kategori']   ?? '');
            $rawUnitName     = trim($row['satuan']     ?? '');

            // Array untuk menampung error di baris ini
            $rowErrors = [];

            // 2. Validasi kolom wajib (kecuali kolom "no" jika ada)
            if ($rawName === '') {
                $rowErrors[] = "Baris {$rowNumber}: kolom 'Nama Produk' wajib diisi.";
            }
            if ($rawPrice === '') {
                $rowErrors[] = "Baris {$rowNumber}: kolom 'Harga' wajib diisi.";
            }
            if ($rawStock === '') {
                $rowErrors[] = "Baris {$rowNumber}: kolom 'Stok' wajib diisi.";
            }
            if ($rawCategoryName === '') {
                $rowErrors[] = "Baris {$rowNumber}: kolom 'Kategori' wajib diisi.";
            }
            if ($rawUnitName === '') {
                $rowErrors[] = "Baris {$rowNumber}: kolom 'Satuan' wajib diisi.";
            }

            // 3. Validasi format Harga (harus integer ≥ 0)
            if ($rawPrice !== '') {
                if (! is_numeric($rawPrice) || intval($rawPrice) != $rawPrice) {
                    $rowErrors[] = "Baris {$rowNumber}: Harga '{$rawPrice}' harus berupa bilangan bulat.";
                } elseif (intval($rawPrice) < 0) {
                    $rowErrors[] = "Baris {$rowNumber}: Harga '{$rawPrice}' tidak boleh kurang dari 0.";
                }
            }

            // 4. Validasi format Stok (harus integer ≥ 0)
            if ($rawStock !== '') {
                if (! is_numeric($rawStock) || intval($rawStock) != $rawStock) {
                    $rowErrors[] = "Baris {$rowNumber}: Stok '{$rawStock}' harus berupa bilangan bulat.";
                } elseif (intval($rawStock) < 0) {
                    $rowErrors[] = "Baris {$rowNumber}: Stok '{$rawStock}' tidak boleh kurang dari 0.";
                }
            }

            // Konversi harga & stok ke integer (jika valid)
            $price = null;
            $stock = null;
            if ($rawPrice !== '' && is_numeric($rawPrice) && intval($rawPrice) == $rawPrice) {
                $price = intval($rawPrice);
            }
            if ($rawStock !== '' && is_numeric($rawStock) && intval($rawStock) == $rawStock) {
                $stock = intval($rawStock);
            }

            // 5. Cek duplikat Nama Produk di dalam sheet (case‐insensitive)
            if ($rawName !== '') {
                $lowerName = mb_strtolower($rawName);
                if (in_array($lowerName, $sheetNamesLower)) {
                    $rowErrors[] = "Baris {$rowNumber}: duplikat Nama Produk '{$rawName}' di sheet.";
                } else {
                    $sheetNamesLower[] = $lowerName;
                }
            }

            // 6. Cek duplikat Nama Produk di database (case‐insensitive)
            if ($rawName !== '') {
                $existsInDb = Product::whereRaw('LOWER(name) = ?', [mb_strtolower($rawName)])->exists();
                if ($existsInDb) {
                    $rowErrors[] = "Baris {$rowNumber}: Nama Produk '{$rawName}' sudah ada di database.";
                }
            }

            // 7. Validasi keberadaan Kategori di tabel categories
            $category = null;
            if ($rawCategoryName !== '') {
                $category = Category::whereRaw('LOWER(name) = ?', [mb_strtolower($rawCategoryName)])->first();
                if (! $category) {
                    $rowErrors[] = "Baris {$rowNumber}: Kategori '{$rawCategoryName}' tidak ditemukan.";
                }
            }

            // 8. Validasi keberadaan Satuan di tabel units
            $unit = null;
            if ($rawUnitName !== '') {
                $unit = Unit::whereRaw('LOWER(name) = ?', [mb_strtolower($rawUnitName)])->first();
                if (! $unit) {
                    $rowErrors[] = "Baris {$rowNumber}: Satuan '{$rawUnitName}' tidak ditemukan.";
                }
            }

            // 9. Jika ada minimal satu error di $rowErrors, catat semuanya lalu skip insert
            if (! empty($rowErrors)) {
                foreach ($rowErrors as $errorMsg) {
                    $this->errors[] = $errorMsg;
                }
                continue;
            }

            // 10. Jika tidak ada error, siapkan data untuk di‐insert
            $this->dataToInsert[] = [
                'name'        => $rawName,
                'price'       => $price,
                'stock'       => $stock,
                'category_id' => $category->id,
                'unit_id'     => $unit->id,
                // 'image'       => null,
            ];
        }

        // 11. Setelah memproses semua baris, jika ada error → throw Exception
        if (! empty($this->errors)) {
            throw new \Exception('Import dibatalkan.');
        }

        // 12. Jika tidak ada error, insert setiap produk yang valid
        foreach ($this->dataToInsert as $data) {
            Product::create($data);
        }
    }
}
