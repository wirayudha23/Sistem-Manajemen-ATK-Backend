<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductTemplate implements
    FromCollection,
    WithHeadings,
    WithColumnFormatting,
    WithStyles,
    WithEvents
{
    public function collection()
    {
        return collect([
            ['No' => 1, 'Nama Produk' => 'Book Holder',     'Harga' => 20000,    'Kategori' => 'Perabotan', 'Satuan' => 'Pcs'],
            ['No' => 2, 'Nama Produk' => 'Buku Catatan A5',     'Harga' => 15000,    'Kategori' => 'Buku', 'Satuan' => 'Buah'],
            ['No' => 3, 'Nama Produk' => 'Penggaris Besi',    'Harga' => 20000,    'Kategori' => 'ATK', 'Satuan' => 'Pcs'],
            ['No' => 4, 'Nama Produk' => 'Bolpen',         'Harga' => 5000,     'Kategori' => 'Alat Tulis', 'Satuan' => 'Pcs'],
        ]);
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama Produk',
            'Harga',
            'Kategori',
            'Satuan',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_NUMBER, // Harga
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $headerRange = 'A1:E1'; // kolom A sampai F

                $sheet->getStyle($headerRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFE5E5E5'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(25);
            },
        ];
    }
}
