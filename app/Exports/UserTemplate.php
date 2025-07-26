<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class UserTemplate implements
    FromCollection,
    WithHeadings,
    WithColumnFormatting,
    WithStyles,
    WithEvents
{
    /**
     * Kembalikan koleksi dengan tiga baris contoh
     */
    public function collection()
    {
        return collect([
            [
                'No'             => 1,
                'Nama'           => 'Alice',
                'Email'          => 'alice@example.com',
                'NIP'            => '123457',
                'Jabatan'        => 'Dosen',
                'Inisial'        => 'ALI',
                'Role'           => 'Staff',
                'No HP'          => '',
                'Program Studi'  => 'Teknik Informatika',
            ],
            [
                'No'             => 2,
                'Nama'           => 'Bob',
                'Email'          => 'bob@example.com',
                'NIP'            => '234567',
                'Jabatan'        => 'Tendik',
                'Inisial'        => 'BOB',
                'Role'           => 'Staff',
                'No HP'          => '',
                'Program Studi'  => '',
            ],
            [
                'No'             => 3,
                'Nama'           => 'Charlie',
                'Email'          => 'charlie@example.com',
                'NIP'            => '345678',
                'Jabatan'        => 'Rumah Tangga',
                'Inisial'        => 'CHA',
                'Role'           => 'Staff',
                'No HP'          => '081238827600',
                'Program Studi'  => '',
            ],
        ]);
    }

    /**
     * Judul kolom
     */
    public function headings(): array
    {
        return [
            'No',
            'Nama',
            'Email',
            'NIP',
            'Jabatan',
            'Inisial',
            'Role',
            'No HP',
            'Program Studi',
        ];
    }

    /**
     * Format kolom: kolom H = No HP sebagai TEXT
     */
    public function columnFormats(): array
    {
        return [
            'H:H' => NumberFormat::FORMAT_TEXT,
        ];
    }

    /**
     * Style sederhana: header bold
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Event AfterSheet untuk border, shading, dan centering header
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet       = $event->sheet->getDelegate();
                $headerRange = 'A1:I1'; // kolom A sampai I

                // border tipis & shading header
                $sheet->getStyle($headerRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFE5E5E5'],
                    ],
                ]);

                // alignment center horizontal & vertical
                $sheet->getStyle($headerRange)->getAlignment()->applyFromArray([
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ]);

                // atur tinggi baris header
                $sheet->getRowDimension(1)->setRowHeight(25);
            },
        ];
    }
}
