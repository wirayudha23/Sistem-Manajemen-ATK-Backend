<?php

namespace App\Http\Controllers;

use App\Imports\CategoryImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class CategoryExcelController extends Controller
{
    public function import(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'file' => 'required|file|mimes:xlsx',
        ], [
            'file.required' => 'File Excel wajib diunggah.',
            'file.file'     => 'File harus berupa file.',
            'file.mimes'    => 'File harus berformat .xlsx',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validasi file gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        $importer = new CategoryImport();

        try {
            Excel::import($importer, $request->file('file'));

            DB::commit();
            return response()->json([
                'status'  => 'success',
                'message' => 'Import berhasil.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Import gagal. Silakan perbaiki kesalahan berikut, lalu coba lagi.',
                'errors'  => $importer->errors,
            ], 422);
        }
    }
}
