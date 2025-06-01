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
     * @param string $startDate format yyyy-mm-dd
     * @param string $endDate   format yyyy-mm-dd
     */
    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate   = $endDate;
    }

    /**
     * Ambil data checkout_details yang berelasi dengan Checkout,
     * difilter berdasarkan checkout_date antara startDate dan endDate.
     */
    public function collection()
    {
        return CheckoutDetail::with(['checkout.user', 'checkout.purpose', 'product'])
            ->whereHas('checkout', function ($q) {
                $q->whereDate('checkout_date', '>=', $this->startDate)
                  ->whereDate('checkout_date', '<=', $this->endDate);
            })
            ->get();
    }

    /**
     * Mapping setiap baris ke format Excel:
     * 1. No
     * 2. checkout_date (d-m-Y)
     * 3. Nama Produk
     * 4. checkout_quantity
     * 5. Nama User
     * 6. Kebutuhan (purpose name)
     * 7. Deskripsi
     */
    public function map($detail): array
    {
        $this->counter++;
        return [
            $this->counter,
            Carbon::parse($detail->checkout->checkout_date)->format('d-m-Y'),
            $detail->product->name,
            $detail->checkout_quantity,
            $detail->checkout->user->name,
            $detail->checkout->purpose->name,
            $detail->checkout->description,
        ];
    }

    /**
     * Headings di baris 3
     */
    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Nama Produk',
            'Jumlah',
            'Nama',
            'Kebutuhan',
            'Deskripsi',
        ];
    }

    /**
     * Mulai tulis data dari sel A3
     */
    public function startCell(): string
    {
        return 'A3';
    }

    /**
     * Styling & layout (merge title, header styling, border, auto-size)
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Judul di A1 sampai G1
                $title = "Data Pengambilan ATK Periode "
                    . Carbon::parse($this->startDate)->format('d-m-Y')
                    . " s/d "
                    . Carbon::parse($this->endDate)->format('d-m-Y');

                $sheet->mergeCells('A1:G1');
                $sheet->setCellValue('A1', $title);
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'name' => 'Times New Roman'],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
                ]);

                // Header baris 3
                $sheet->getStyle('A3:G3')->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Times New Roman'],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D3D3D3'],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Border seluruh tabel
                $highestRow    = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                $range         = 'A3:' . $highestColumn . $highestRow;
                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Alignment khusus kolom
                $sheet->getStyle('A4:A' . $highestRow)
                      ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B4:B' . $highestRow)
                      ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D4:D' . $highestRow)
                      ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                // Kolom E (Nama User), F (Kebutuhan), G (Deskripsi) kita ratakan kiri
                foreach (['C','E','F','G'] as $col) {
                    $sheet->getStyle("{$col}4:{$col}{$highestRow}")
                          ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                }
                // Kolom C (Nama Produk) juga left, sudah termasuk di atas

                // Auto-size kolom
                foreach (range('A', $highestColumn) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
