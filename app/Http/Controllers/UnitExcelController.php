<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Imports\UnitImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\CategoryImport;
use Illuminate\Support\Facades\Log;

class UnitExcelController extends Controller
{
    /**
     * Import kategori via file XLSX
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx',
        ]);

        $importer = new UnitImport();

        try {
            Excel::import($importer, $request->file('file'));

            return response()->json([
                'status' => 'success',
                'message' => 'Import berhasil.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Import gagal.',
                'errors' => $importer->errors,
            ], 422);
        }
    }
}
