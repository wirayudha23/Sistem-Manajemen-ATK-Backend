<?php

namespace App\Http\Controllers;

use App\Imports\ProductImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ProductExcelController extends Controller
{
    /**
     * POST /products/import
     * Request: multipart/form‐data { file: (xls/xlsx) }
     */
    public function import(Request $request)
    {
        // 1. Validasi awal: pastikan ada file Excel
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

        // 2. Mulai transaksi
        DB::beginTransaction();

        // 3. Instansiasi importer dan coba import
        $importer = new ProductImport();

        try {
            Excel::import($importer, $request->file('file'));

            // Jika sampai sini tidak throw, berarti semua baris valid dan sudah tersimpan
            DB::commit();
            return response()->json([
                'status'  => 'success',
                'message' => 'Import produk berhasil.',
            ], 201);

        } catch (\Exception $e) {
            // 4. Bila ada Exception dari ProductImport (atau exception lain), rollback.
            DB::rollBack();


            // 5. Kembalikan JSON yang memuat daftar semua error yang dikumpulkan importer
            return response()->json([
                'status'  => 'error',
                'message' => 'Import produk gagal. Silakan perbaiki kesalahan berikut, lalu coba lagi.' . $e->getMessage(),
                'errors'  => $importer->errors,
            ], 422);
        }
    }
}
