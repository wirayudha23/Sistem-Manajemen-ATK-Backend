<?php

namespace App\Exports;

use App\Models\CheckoutDetail;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class CheckoutExport implements FromCollection, WithMapping, WithHeadings, WithCustomStartCell, WithEvents
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
     * Ambil data checkout detail yang berelasi dengan checkout,
     * filter berdasarkan checkout_date pada tabel checkout.
     */
    public function collection()
    {
        return CheckoutDetail::with('checkout.user', 'product')
            ->whereHas('checkout', function ($q) {
                $q->whereBetween('checkout_date', [$this->startDate, $this->endDate]);
            })->get();
    }

    /**
     * Mapping setiap baris data untuk diexport ke Excel.
     * Setiap baris mewakili satu item checkout detail.
     */
    public function map($checkoutDetail): array
    {
        $this->counter++;

        return [
            $this->counter,
            Carbon::parse($checkoutDetail->checkout->checkout_date)->format('d-m-Y'),
            $checkoutDetail->product->name,
            $checkoutDetail->checkout_quantity,
            $checkoutDetail->checkout->user->initial,
        ];
    }

    /**
     * Header kolom yang akan ditampilkan di baris 3.
     */
    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Nama Barang',
            'Jumlah',
            'Inisial'
        ];
    }

    /**
     * Mengatur agar header (dan data) mulai ditulis dari sel A3.
     */
    public function startCell(): string
    {
        return 'A3';
    }

    /**
     * Mendaftarkan event AfterSheet untuk mengatur styling dan layout file Excel.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;

                // --- Judul di Baris 1 ---
                // Tentukan judul
                $title = "Data Pengambilan ATK pada BAAK PCR periode " .
                    Carbon::parse($this->startDate)->format('d-m-Y') .
                    " - " .
                    Carbon::parse($this->endDate)->format('d-m-Y');

                // Hitung range merge. Misal, jika $approxColCount = 3, maka merge A1:C1.
                // Kita asumsikan kolom dimulai dari A.
                $sheet->getDelegate()->mergeCells('A1:H1');
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
                // Styling header baris 3: Bold, Times New Roman, center alignment, background light gray, border
                $sheet->getStyle('A3:E3')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'name' => 'Times New Roman',
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => 'D3D3D3', // light gray
                        ],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Tentukan rentang data (header dan data) berdasarkan sel tertinggi
                $highestRow = $sheet->getDelegate()->getHighestRow();
                $highestColumn = $sheet->getDelegate()->getHighestColumn();
                $range = 'A3:' . $highestColumn . $highestRow;

                // Terapkan border untuk seluruh tabel (header dan data)
                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // --- Styling Kolom Data (Mulai Baris 4) ---
                // Kolom No (A), Tanggal (B), Jumlah (D), Inisial (E) center
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
                $sheet->getStyle('D4:D' . $highestRow)->applyFromArray([
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
                $sheet->getStyle('E4:E' . $highestRow)->applyFromArray([
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                // Kolom Nama Barang (C) tetap align left
                $sheet->getStyle('C4:C' . $highestRow)->applyFromArray([
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                    ],
                ]);

                // --- Auto Column Width ---
                foreach (range('A', $highestColumn) as $col) {
                    $sheet->getDelegate()->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
