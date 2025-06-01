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
            $rowNumber = $index + 2; // Header di baris 1, data mulai baris 2

            // 1. Baca dan trim header yang baru:
            $rawPos     = trim($row['jabatan']      ?? '');
            $rawProgram = trim($row['program_studi'] ?? '');
            $name       = trim($row['nama']          ?? '');
            $email      = trim($row['email']         ?? '');
            $nip        = trim($row['nip']           ?? '');
            $initial    = trim($row['inisial']       ?? '');
            $role       = trim($row['role']          ?? '');
            $phone      = trim($row['no_hp']         ?? '');

            // Normalisasi (case-insensitive)
            $position = ucwords(strtolower($rawPos));    // e.g. "jabatan" → "Jabatan"
            $program  = ucwords(strtolower($rawProgram)); // untuk lookup StudyProgram

            // Kumpulkan semua error per-baris
            $rowErrors = [];

            // 2. Validasi role harus "Staff"
            if (strtolower($role) !== 'staff') {
                $rowErrors[] = "Baris {$rowNumber}: role harus 'Staff'.";
            }

            // 3. Validasi kolom wajib: nama, email, nip, jabatan, inisial
            if (!$name || !$email || !$nip || !$position || !$initial) {
                $rowErrors[] = "Baris {$rowNumber}: kolom wajib (nama, email, nip, jabatan, inisial) belum lengkap.";
            }

            // 4. Validasi jabatan
            $allowedPositions = ['Dosen', 'Tendik', 'Rumah Tangga'];
            if ($position && ! in_array($position, $allowedPositions)) {
                $rowErrors[] = "Baris {$rowNumber}: jabatan '{$rawPos}' tidak valid.";
            }

            // 5. Validasi email
            if ($email && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = "Baris {$rowNumber}: format email '{$email}' tidak valid.";
            }

            // 6. Cek duplikat email di sheet & database
            if ($email) {
                if (in_array(strtolower($email), $emails)) {
                    $rowErrors[] = "Baris {$rowNumber}: duplikat email '{$email}' di sheet.";
                }
                if (User::whereRaw('LOWER(email) = ?', [strtolower($email)])->exists()) {
                    $rowErrors[] = "Baris {$rowNumber}: email '{$email}' sudah terdaftar.";
                }
            }

            // 7. Cek duplikat nama, nip, inisial di database
            if ($name && User::whereRaw('LOWER(name) = ?', [strtolower($name)])->exists()) {
                $rowErrors[] = "Baris {$rowNumber}: nama '{$name}' sudah ada.";
            }
            if ($nip && User::where('nip', $nip)->exists()) {
                $rowErrors[] = "Baris {$rowNumber}: nip '{$nip}' sudah ada.";
            }
            if ($initial && User::whereRaw('LOWER(initial) = ?', [strtolower($initial)])->exists()) {
                $rowErrors[] = "Baris {$rowNumber}: inisial '{$initial}' sudah ada.";
            }

            // Setelah cek di database, tambahkan ke array $emails agar pengecekan duplikat sheet benar
            if ($email && ! in_array(strtolower($email), $emails)) {
                $emails[] = strtolower($email);
            }

            // 8. Phone hanya untuk "Rumah Tangga"
            $phoneNumber = null;
            if ($position === 'Rumah Tangga') {
                if (empty($phone)) {
                    $rowErrors[] = "Baris {$rowNumber}: No HP wajib untuk jabatan Rumah Tangga.";
                } elseif (! preg_match('/^08\d{9,10}$/', $phone)) {
                    $rowErrors[] = "Baris {$rowNumber}: format No HP '{$phone}' tidak valid.";
                } elseif (User::whereRaw('LOWER(phone_number) = ?', [strtolower($phone)])->exists()) {
                    $rowErrors[] = "Baris {$rowNumber}: No HP '{$phone}' sudah ada.";
                } else {
                    $phoneNumber = $phone;
                }
            }

            // 9. Lookup Program Studi (khusus jabatan "Dosen")
            $programId = null;
            if ($position === 'Dosen') {
                if (empty($program)) {
                    $rowErrors[] = "Baris {$rowNumber}: Program Studi wajib untuk jabatan Dosen.";
                } else {
                    // Tabel study_programs kolomnya "name"
                    $sp = StudyProgram::whereRaw('LOWER(name) = ?', [strtolower($program)])->first();
                    if (! $sp) {
                        $rowErrors[] = "Baris {$rowNumber}: Program Studi '{$program}' tidak ditemukan.";
                    } else {
                        $programId = $sp->id;
                    }
                }
            }

            // 10. Jika ada error, kumpulkan ke $this->errors lalu lanjutkan ke baris selanjutnya
            if (! empty($rowErrors)) {
                foreach ($rowErrors as $errorMsg) {
                    $this->errors[] = $errorMsg;
                }
                continue;
            }

            // 11. Jika valid, siapkan data untuk di-insert
            $this->dataToInsert[] = [
                'name'             => $name,           // kolom di DB: name
                'email'            => $email,
                'nip'              => $nip,
                'position'         => $position,
                'initial'          => $initial,
                'role'             => 'Staff',
                'phone_number'     => $phoneNumber,
                'study_program_id' => $programId,
            ];
        }

        // 12. Jika ada error, batalkan semua
        if (count($this->errors)) {
            throw new \Exception('Import dibatalkan.');
        }

        // 13. Simpan semua data valid
        foreach ($this->dataToInsert as $data) {
            User::create($data);
        }
    }
}
