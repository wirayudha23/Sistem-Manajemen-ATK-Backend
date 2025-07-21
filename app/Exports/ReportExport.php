<?php

namespace App\Exports;

use App\Models\FundTransaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents
{
    protected $startDate;
    protected $endDate;

    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate   = $endDate;
    }

    public function collection()
    {
        $txs = FundTransaction::with('productReceived.details.product')
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->orderBy('date')
            ->get();

        $rows = [];
        $balance = 0;
        $counter = 1;
        $sumMasuk = 0;
        $sumKeluar = 0;

        foreach ($txs as $tx) {
            if ($tx->type === 'in') {
                $balance += $tx->amount;
                $sumMasuk += $tx->amount;
                $rows[] = [
                    $counter++,
                    $tx->date->format('d/m/Y'),
                    'Dana masuk',
                    '',
                    $tx->amount,
                    '',
                    $balance,
                ];
            } else {
                foreach ($tx->productReceived->details as $detail) {
                    $balance -= $detail->total_product_price;
                    $sumKeluar += $detail->total_product_price;
                    $rows[] = [
                        $counter++,
                        $tx->date->format('d/m/Y'),
                        $detail->product->name,
                        $detail->received_quantity,
                        '',
                        $detail->total_product_price,
                        $balance,
                    ];
                }
            }
        }

        // Tambahkan baris total di akhir
        $rows[] = [
            'TOTAL', // Akan digabungkan dari kolom A sampai C
            '',
            '',
            '',
            $sumMasuk,
            $sumKeluar,
            $balance,
        ];

        return collect($rows);
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Keterangan',
            'Jumlah',
            'Masuk',
            'Keluar',
            'Sisa',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // Style header: bold & center
                $styleHeader = [
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ];
                $sheet->getStyle('A1:G1')->applyFromArray($styleHeader);

                // Style total row: bold
                $styleTotal = [
                    'font' => ['bold' => true],
                ];
                $sheet->getStyle("A{$highestRow}:G{$highestRow}")->applyFromArray($styleTotal);

                // Tambahkan border ke seluruh tabel
                $styleBorder = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ];
                $sheet->getStyle("A1:G{$highestRow}")->applyFromArray($styleBorder);

                // Center align kolom No dan Jumlah
                $sheet->getStyle("A2:A{$highestRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D2:D{$highestRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Merge kolom A-C pada baris total
                $sheet->mergeCells("A{$highestRow}:C{$highestRow}");
                $sheet->getStyle("A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
