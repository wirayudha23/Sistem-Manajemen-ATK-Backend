<?php

namespace App\Imports;

use App\Models\Unit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UnitImport implements ToCollection, WithHeadingRow
{
    public array $errors = [];

    protected array $dataToInsert = [];

    public function collection(Collection $rows)
    {
        $sheetNamesLower = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // Baris header di baris 1, data mulai baris 2

            // 1. Baca dan trim nama satuan (header Excel: "Nama Satuan")
            $rawName = trim($row['nama_satuan'] ?? '');

            // Array lokal untuk menampung semua error di baris ini
            $rowErrors = [];

            // 2. Validasi kosong
            if ($rawName === '') {
                $rowErrors[] = "Baris {$rowNumber}: kolom 'Nama Satuan' kosong.";
            }

            // 3. Jika tidak kosong, cek duplikat di sheet (case‐insensitive)
            if ($rawName !== '') {
                $lowerName = mb_strtolower($rawName);
                if (in_array($lowerName, $sheetNamesLower)) {
                    $rowErrors[] = "Baris {$rowNumber}: duplikat nama satuan '{$rawName}' di sheet.";
                } else {
                    $sheetNamesLower[] = $lowerName;
                }
            }

            // 4. Jika tidak kosong, cek duplikat di database (case‐insensitive)
            if ($rawName !== '') {
                if (Unit::whereRaw('LOWER(name) = ?', [mb_strtolower($rawName)])->exists()) {
                    $rowErrors[] = "Baris {$rowNumber}: nama satuan '{$rawName}' sudah ada di database.";
                }
            }

            // 5. Jika ada error di baris ini, kumpulkan semua pesan dan lanjut ke baris berikutnya
            if (! empty($rowErrors)) {
                foreach ($rowErrors as $errorMsg) {
                    $this->errors[] = $errorMsg;
                }
                continue;
            }

            // 6. Jika tidak ada error, siapkan data untuk di‐insert
            $this->dataToInsert[] = ['name' => $rawName];
        }

        // 7. Setelah memproses semua baris, jika ada minimal satu error → batalkan import
        if (! empty($this->errors)) {
            throw new \Exception('Import dibatalkan.');
        }

        // 8. Jika tidak ada error sama sekali, lakukan insert semua data valid
        foreach ($this->dataToInsert as $data) {
            Unit::create($data);
        }
    }
}
