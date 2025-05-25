<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UnitImport implements ToCollection, WithHeadingRow
{
    public array $errors = [];

    /**
     * Proses koleksi baris dari file XLSX
     *
     * @param Collection $rows
     * @throws \Exception Jika ada duplikat di sheet atau di database
     */
    public function collection(Collection $rows)
    {
        $sheetNames = [];
        $dataToInsert = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // Heading di baris 1, data mulai baris 2
            $name = trim($row['nama_satuan'] ?? '');

            // Validasi kosong
            if (empty($name)) {
                $this->errors[] = "Baris {$rowNumber}: kolom 'Nama Satuan' kosong.";
                continue;
            }

            // Validasi duplikat di sheet
            if (in_array($name, $sheetNames)) {
                $this->errors[] = "Baris {$rowNumber}: duplikat nama satuan '{$name}' di sheet.";
                continue;
            }
            $sheetNames[] = $name;

            // Validasi duplikat di database
            if (Unit::where('name', $name)->exists()) {
                $this->errors[] = "Baris {$rowNumber}: nama satuan '{$name}' sudah ada di database.";
                continue;
            }

            $dataToInsert[] = ['name' => $name];
        }

        if (count($this->errors) > 0) {
            throw new \Exception("Import dibatalkan. Ada kesalahan:", 1);
        }

        foreach ($dataToInsert as $data) {
            Unit::create($data);
        }
    }
}
