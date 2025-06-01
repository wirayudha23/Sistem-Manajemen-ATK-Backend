<?php

namespace App\Exports;

use App\Models\ProductReceivedDetail;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ReorderExport implements FromCollection, WithMapping, WithHeadings, WithCustomStartCell, WithEvents
{
    protected $startDate;
    protected $endDate;
    protected $counter = 0;

    /**
     * Konstruktor menerima parameter tanggal awal dan tanggal akhir.
     *
     * @param string $startDate (format: yyyy-mm-dd)
     * @param string $endDate   (format: yyyy-mm-dd)
     */
    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate   = $endDate;
    }

    /**
     * Ambil data detail penerimaan produk (ProductReceivedDetail) yang berelasi dengan ProductReceived.
     * Data difilter berdasarkan received_date pada model ProductReceived.
     */
    public function collection()
    {
        return ProductReceivedDetail::with('productReceived', 'product')
            ->whereHas('productReceived', function ($q) {
                $q->whereBetween('received_date', [$this->startDate, $this->endDate]);
            })

            ->where('received_quantity', '>', 0)
            ->get();
    }

    /**
     * Mapping setiap baris data untuk diekspor ke Excel.
     * Setiap baris akan menampilkan:
     * 1. Nomor urut
     * 2. received_date (diformat 'd-m-Y')
     * 3. Nama Product (mengambil field name dari relasi product)
     * 4. received_quantity
     * 5. price
     * 6. total_product_price
     */
    public function map($productReceivedDetail): array
    {
        $this->counter++;
        return [
            $this->counter,
            Carbon::parse($productReceivedDetail->productReceived->received_date)->format('d-m-Y'),
            $productReceivedDetail->product->name,
            $productReceivedDetail->received_quantity,
            $productReceivedDetail->price,
            $productReceivedDetail->total_product_price,
        ];
    }

    /**
     * Header kolom yang ditampilkan pada baris 3.
     */
    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Nama Produk',
            'Jumlah',
            'Harga',
            'Total Harga Produk',
        ];
    }

    /**
     * Data dan header akan ditulis mulai dari sel A3.
     */
    public function startCell(): string
    {
        return 'A3';
    }

    /**
     * Mendaftarkan event AfterSheet untuk mengatur styling, layout, dan menambahkan total keseluruhan harga.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;

                // --- Judul di Baris 1 ---
                $title = "Data Penerimaan Produk Periode " .
                    Carbon::parse($this->startDate)->format('d-m-Y') .
                    " s/d " .
                    Carbon::parse($this->endDate)->format('d-m-Y');
                // Merge judul dari kolom A sampai F
                $sheet->getDelegate()->mergeCells('A1:F1');
                $sheet->setCellValue('A1', $title);
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14,
                        'name' => 'Times New Roman',
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                // --- Header di Baris 3 ---
                // Styling header baris 3: bold, Times New Roman, center alignment, background light gray, border
                $sheet->getStyle('A3:F3')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'name' => 'Times New Roman',
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => 'D3D3D3',
                        ],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Tentukan range data (header dan data)
                $highestRow = $sheet->getDelegate()->getHighestRow();
                $highestColumn = $sheet->getDelegate()->getHighestColumn();
                $range = 'A3:' . $highestColumn . $highestRow;

                // Terapkan border pada seluruh tabel data
                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // --- Styling Kolom Data (Mulai Baris 4) ---
                // Kolom nomor (A), Received Date (B), Price (E), Total Product Price (F) diberi center alignment.
                $sheet->getStyle('A4:A' . $highestRow)->applyFromArray([
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
                $sheet->getStyle('B4:B' . $highestRow)->applyFromArray([
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
                $sheet->getStyle('E4:E' . $highestRow)->applyFromArray([
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
                $sheet->getStyle('F4:F' . $highestRow)->applyFromArray([
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
                $sheet->getStyle('D4:D' . $highestRow)->applyFromArray([
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                // Kolom Product Name (C) dan Received Quantity (D) align left
                $sheet->getStyle('C4:C' . $highestRow)->applyFromArray([
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                    ],
                ]);

                // --- Auto Column Width ---
                foreach (range('A', $highestColumn) as $col) {
                    $sheet->getDelegate()->getColumnDimension($col)->setAutoSize(true);
                }

                // --- Menambahkan Baris Total Keseluruhan Harga di Bawah Data ---
                // Baris total berada tepat setelah baris data terakhir
                $totalRow = $highestRow + 1;
                // Teks "TOTAL" akan kita letakkan di kolom E, total keseluruhan harga di kolom F
                $sheet->setCellValue('E' . $totalRow, 'TOTAL');

                // Menghitung total keseluruhan harga dari kolom "Total Product Price" (kolom F, mulai baris 4 sampai baris terakhir data)
                $sheet->setCellValue('F' . $totalRow, '=SUM(F4:F' . $highestRow . ')');

                // Styling untuk baris total
                $sheet->getStyle('E' . $totalRow . ':F' . $totalRow)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'name' => 'Times New Roman',
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);
            },
        ];
    }
}
