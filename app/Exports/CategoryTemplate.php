<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Collection;

class CategoryTemplate implements FromCollection, WithHeadings, WithColumnFormatting, WithStyles, WithEvents
{
    public function collection()
    {
        return collect([
            ['No' => 1, 'Nama Kategori' => 'Elektronik'],
        ]);
    }

    public function headings(): array
    {
        return ['No', 'Nama Kategori'];
    }

    public function columnFormats(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        // Bold header row
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $e) {
                $sheet = $e->sheet->getDelegate();
                $headerRange = 'A1:B1';

                // Border dan shading header
                $sheet->getStyle($headerRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFE5E5E5'],
                    ],
                ]);

                // Alignment center horizontal & vertical
                $sheet->getStyle($headerRange)->getAlignment()->applyFromArray([
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ]);

                // Optional: set row tinggi agar pas
                $sheet->getRowDimension(1)->setRowHeight(25);
            },
        ];
    }
}
