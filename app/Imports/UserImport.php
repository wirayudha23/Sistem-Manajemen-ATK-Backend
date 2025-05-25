<?php

namespace App\Imports;

use App\Models\User;
use App\Models\StudyProgram;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UserImport implements ToCollection, WithHeadingRow
{
    public array $errors = [];
    protected array $dataToInsert = [];

    public function collection(Collection $rows)
    {
        $emails = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            // Baca dan trim semua kolom mentah
            $rawPos   = trim($row['position'] ?? '');
            $rawProgram = trim($row['program_studi'] ?? '');
            $name     = trim($row['name'] ?? '');
            $email    = trim($row['email'] ?? '');
            $nip      = trim($row['nip'] ?? '');
            $initial  = trim($row['initial'] ?? '');
            $role     = trim($row['role'] ?? '');
            $program  = trim($row['program_studi'] ?? '');
            $phone    = trim($row['phone_number'] ?? '');

            // Normalisasi position (case-insensitive)
            $position = ucwords(strtolower($rawPos));
            // e.g. 'dosen', 'DOSEN', 'DoSeN' → 'Dosen'

            $program = ucwords(strtolower($rawProgram));

            // 1. Pastikan role hanya 'Staff'
            if (strtolower($role) !== 'staff') {
                $this->errors[] = "Baris {$rowNumber}: role harus 'Staff'.";
                continue;
            }

            // 2. Validasi kolom wajib
            if (!$name || !$email || !$nip || !$position || !$initial) {
                $this->errors[] = "Baris {$rowNumber}: kolom wajib (name, email, nip, position, initial) kosong.";
                continue;
            }

            // 3. Validasi position termasuk dalam daftar
            $allowedPositions = ['Dosen', 'Tendik', 'Rumah Tangga'];
            if (! in_array($position, $allowedPositions)) {
                $this->errors[] = "Baris {$rowNumber}: posisi '{$rawPos}' tidak valid.";
                continue;
            }

            // 4. Validasi format email
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Baris {$rowNumber}: format email '{$email}' tidak valid.";
                continue;
            }

            // 5. Cek duplikat di sheet & database
            if (in_array(strtolower($email), $emails)) {
                $this->errors[] = "Baris {$rowNumber}: duplikat email '{$email}' di sheet.";
                continue;
            }
            $emails[] = strtolower($email);

            if (User::where('email', $email)->exists()) {
                $this->errors[] = "Baris {$rowNumber}: email '{$email}' sudah terdaftar.";
                continue;
            }
            if (User::where('name', $name)->exists()) {
                $this->errors[] = "Baris {$rowNumber}: name '{$name}' sudah ada.";
                continue;
            }
            if (User::where('nip', $nip)->exists()) {
                $this->errors[] = "Baris {$rowNumber}: nip '{$nip}' sudah ada.";
                continue;
            }
            if (User::where('initial', $initial)->exists()) {
                $this->errors[] = "Baris {$rowNumber}: initial '{$initial}' sudah ada.";
                continue;
            }

            // 6. Phone only for Rumah Tangga
            if ($position === 'Rumah Tangga') {
                if (empty($phone)) {
                    $this->errors[] = "Baris {$rowNumber}: phone_number wajib untuk posisi Rumah Tangga.";
                    continue;
                }
                if (! preg_match('/^08\d{9,10}$/', $phone)) {
                    $this->errors[] = "Baris {$rowNumber}: format phone_number '{$phone}' tidak valid.";
                    continue;
                }
                if (User::where('phone_number', $phone)->exists()) {
                    $this->errors[] = "Baris {$rowNumber}: phone_number '{$phone}' sudah ada.";
                    continue;
                }
            } else {
                $phone = null;
            }

            // 7. Lookup Program Studi by name (case‐insensitive)
            if ($position === 'Dosen') {
                if (empty($program)) {
                    $this->errors[] = "Baris {$rowNumber}: Program Studi wajib untuk posisi Dosen.";
                    continue;
                }
                $sp = StudyProgram::whereRaw('LOWER(name) = ?', [strtolower($program)])->first();
                if (! $sp) {
                    $this->errors[] = "Baris {$rowNumber}: Program Studi '{$program}' tidak ditemukan.";
                    continue;
                }
                $programId = $sp->id;
            } else {
                $programId = null;
            }

            // Siapkan data untuk di-insert
            $this->dataToInsert[] = [
                'name'             => $name,
                'email'            => $email,
                'nip'              => $nip,
                'position'         => $position,
                'initial'          => $initial,
                'role'             => 'Staff',      // pasti Staff
                'phone_number'     => $phone,
                'study_program_id' => $programId,
            ];
        }

        // Abort jika ada error
        if (count($this->errors)) {
            throw new \Exception('Import dibatalkan.');
        }

        // Simpan semua data
        foreach ($this->dataToInsert as $data) {
            User::create($data);
        }
    }
}
