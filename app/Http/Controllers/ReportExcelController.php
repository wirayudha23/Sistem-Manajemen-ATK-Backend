<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportExcelController extends Controller
{
    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        // Parse dan format tanggal ke d-m-Y
        $start = Carbon::parse($request->input('start_date'))->format('d-m-Y');
        $end   = Carbon::parse($request->input('end_date'))->format('d-m-Y');

        // Nama file: Report_{start}_{end}.xlsx
        $fileName = "Report {$start} {$end}.xlsx";

        return Excel::download(
            new ReportExport(
                // untuk ReportExport tetap pakai format Y-m-d agar query whereBetween benar
                $request->input('start_date'),
                $request->input('end_date')
            ),
            $fileName
        );
    }
}
