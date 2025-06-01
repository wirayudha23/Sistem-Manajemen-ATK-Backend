<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Exports\CheckoutExport;
use Maatwebsite\Excel\Facades\Excel;

class CheckoutExcelController extends Controller
{
    /**
     * Export checkout data to Excel.
     */
    public function export(Request $request)
    {
        $request->validate([
        'start_date' => 'required|date',
        'end_date'   => 'required|date|after_or_equal:start_date',
    ]);

    $start = $request->query('start_date');
    $end   = $request->query('end_date');

    $fileName = 'Data Pengambilan ATK Periode '
        . Carbon::parse($start)->format('d-m-Y')
        . ' - '
        . Carbon::parse($end)->format('d-m-Y')
        . '.xlsx';

    return Excel::download(new CheckoutExport($start, $end), $fileName);
    }

    /**
     * Import checkout data from Excel.
     */
    public function import()
    {
        // Implement import logic here
    }
}
