<?php

namespace App\Imports;

use App\Models\Category;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CategoryImport implements ToCollection, WithHeadingRow
{
    public array $errors = [];
    protected array $dataToInsert = [];

    public function collection(Collection $rows)
    {
        $sheetNamesLower = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // Heading di baris 1, data mulai baris 2

            // 1. Baca dan trim nama kategori (header Excel: "Nama Kategori")
            $rawName = trim($row['nama_kategori'] ?? '');

            // 2. Siapkan array lokal untuk menampung semua error di baris ini
            $rowErrors = [];

            // 3. Validasi kosong
            if ($rawName === '') {
                $rowErrors[] = "Baris {$rowNumber}: kolom 'Nama Kategori' kosong.";
            }

            // 4. Validasi duplikat di sheet (case‐insensitive)
            if ($rawName !== '') {
                $lowerName = mb_strtolower($rawName);
                if (in_array($lowerName, $sheetNamesLower)) {
                    $rowErrors[] = "Baris {$rowNumber}: duplikat nama kategori '{$rawName}' di sheet.";
                } else {
                    $sheetNamesLower[] = $lowerName;
                }
            }

            // 5. Validasi duplikat di database
            if ($rawName !== '') {
                if (Category::whereRaw('LOWER(name) = ?', [mb_strtolower($rawName)])->exists()) {
                    $rowErrors[] = "Baris {$rowNumber}: nama kategori '{$rawName}' sudah ada di database.";
                }
            }

            // 6. Jika ada error di baris ini, pindahkan semua ke $this->errors dan lewati insert
            if (! empty($rowErrors)) {
                foreach ($rowErrors as $msg) {
                    $this->errors[] = $msg;
                }
                continue;
            }

            // 7. Jika tidak ada error, simpan ke array untuk di‐insert nanti
            $this->dataToInsert[] = ['name' => $rawName];
        }

        // 8. Setelah memproses semua baris, jika ada error → batalkan import
        if (! empty($this->errors)) {
            throw new \Exception('Import dibatalkan.');
        }

        // 9. Jika tidak ada error sama sekali, lakukan insert semua
        foreach ($this->dataToInsert as $data) {
            Category::create($data);
        }
    }
}
