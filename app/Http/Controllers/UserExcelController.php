<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UserImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserExcelController extends Controller
{
    public function import(Request $request)
    {
        // Validasi file Excel
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls',
        ], [
            'file.required' => 'File Excel wajib diunggah.',
            'file.file'     => 'File harus berupa file Excel.',
            'file.mimes'    => 'File harus berformat .xlsx atau .xls',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validasi file gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        $importer = new UserImport();

        try {
            Excel::import($importer, $request->file('file'));

            // Jika tidak ada exception, commit
            DB::commit();
            return response()->json([
                'status'  => 'success',
                'message' => 'Import user berhasil.',
            ], 201);

        } catch (\Exception $e) {
            // Rollback apabila ada error
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Import user gagal. Silakan perbaiki kesalahan berikut, lalu coba lagi. ',
                'errors'  => $importer->errors,
            ], 422);
        }
    }
}
