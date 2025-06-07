<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Exports\CheckoutExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\CheckoutImport;
use Illuminate\Support\Facades\Log;

class CheckoutExcelController extends Controller
{
    /**
     * Export checkout data to Excel.
     */
    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $start = $request->query('start_date');
        $end = $request->query('end_date');

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
    public function import(Request $request)
    {
        // 1) Validasi file upload
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ], [
            'file.required' => 'File import wajib diunggah.',
            'file.file' => 'Unggahan harus berupa file.',
            'file.mimes' => 'Format file hanya boleh xlsx, xls, atau csv.',
        ]);

        try {
            // 2) Jalankan import, akan memanggil CheckoutImport::collection
            Excel::import(new CheckoutImport, $request->file('file'));

            // 3) Jika semua baris berhasil disimpan
            return response()->json([
                'status' => 'success',
                'message' => 'Import checkout berhasil.',
            ], 201);

        } catch (\Exception $e) {
            // 4) Tangkap exception, pesan isi bisa berupa JSON‐encoded array error baris
            $raw = $e->getMessage();
            $errorsArray = [];

            if ($decoded = json_decode($raw, true)) {
                // Jika berhasil decode, $decoded adalah array pesan error
                $errorsArray = $decoded;
            } else {
                // Jika bukan JSON, jadikan array berisi satu elemen
                $errorsArray[] = $raw;
            }

            // Log error (opsional)
            Log::error('Error saat import checkout: ', $errorsArray);

            // 5) Return response sesuai format yang diminta
            return response()->json([
                'status' => 'error',
                'message' => 'Import checkout gagal. Silakan perbaiki kesalahan berikut, lalu coba lagi. Import dibatalkan.',
                'errors' => $errorsArray,
            ], 422);
        }
    }
}
