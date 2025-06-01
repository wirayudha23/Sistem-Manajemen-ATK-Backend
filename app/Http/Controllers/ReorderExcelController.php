<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Exports\CheckoutExport;
use App\Exports\ReorderExport;
use App\Imports\CheckoutImport;
use Maatwebsite\Excel\Facades\Excel;

class ReorderExcelController extends Controller
{
    public function export(Request $request)
    {
        try {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $startFormatted = Carbon::parse($startDate)->format('d-m-Y');
            $endFormatted = Carbon::parse($endDate)->format('d-m-Y');
            $filename = "Data Pengambilan ATK Periode {$startFormatted} - {$endFormatted}.xlsx";

            return Excel::download(new ReorderExport($startDate, $endDate), $filename);
        } catch (\Exception $e) {
            Log::error('Error exporting checkout data: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error exporting data: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);
        try {
            $import = new CheckoutImport();
            Excel::import($import, $request->file('file'));

            return response()->json([
                'message' => 'Data imported successfully.',
                'errors' => $import->errors,
            ]);
        } catch (\Exception $e) {
            Log::error('Import error: ' . $e->getMessage());
            $errorMessage = explode("\n", $e->getMessage());
            return response()->json([
                'message' => 'Import Data Pengambilan ATK Gagal',
                'error' => $errorMessage,
            ], 500);
        }
    }
}
