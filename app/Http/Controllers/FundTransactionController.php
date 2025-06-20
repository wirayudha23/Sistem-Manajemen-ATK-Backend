<?php

namespace App\Http\Controllers;

use App\Models\FundTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class FundTransactionController extends Controller
{
    public function index(Request $request)
    {
        // 1. Validasi input year & month
        $req = $request->validate([
            'year' => 'required|digits:4|integer',
            'month' => 'required|digits_between:1,2|integer|min:1|max:12',
        ]);

        $year = $req['year'];
        $month = $req['month'];

        // 2. Hitung aggregate
        $in = FundTransaction::where('type', 'in')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('amount');

        $out = FundTransaction::where('type', 'out')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('amount');

        $balance = FundTransaction::monthlyBalance($year, $month);

        // 3. Ambil daftar transaksi untuk periode tersebut
        $transactions = FundTransaction::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date', 'asc')
            ->get(['id', 'type', 'date', 'amount']);

        // 4. Kembalikan response JSON, termasuk daftar transaksi
        return response()->json([
            'status' => 'success',
            'message' => 'Data transaksi dana berhasil diambil.',
            'year' => $year,
            'month' => $month,
            'in' => $in,
            'out' => $out,
            'balance' => $balance,
            'transactions' => $transactions,
        ]);
    }


    public function store(Request $request)
    {
        try {
            $validated = $request->validate(
                [
                    'date' => 'required|date_format:d-m-Y',
                    'amount' => 'required|integer|min:1',
                ],
                [
                    'date.required' => 'Tanggal tidak boleh kosong.',
                    'date.date_format' => 'Format tanggal harus dd-mm-yyyy.',
                    'amount.required' => 'Jumlah dana tidak boleh kosong.',
                    'amount.integer' => 'Jumlah dana harus berupa angka.',
                    'amount.min' => 'Jumlah dana minimal 1.',
                ]
            );

            $tx = FundTransaction::create([
                'id' => (string) Str::uuid(),
                // 'date'                  => \Carbon\Carbon::createFromFormat('d-m-Y', $validated['date']),
                'date' => now(),
                'type' => 'in',
                'amount' => $validated['amount'],
                'product_received_id' => null,
            ]);

            return response()->json([
                'message' => 'Dana masuk berhasil ditambahkan.',
                'data' => $tx,
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Error during fund transaction creation: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan saat mencatat dana masuk.',
                'error' => $e->getMessage(), // Opsional, untuk debugging
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $tx = FundTransaction::findOrFail($id);

            if ($tx->type !== 'in') {
                return response()->json([
                    'message' => 'Hanya transaksi dana masuk yang bisa diubah.'
                ], 403);
            }

            $validated = $request->validate([
                // Pakai "sometimes" agar validasi hanya dijalankan jika key ada di payload
                'date' => 'sometimes|required|date_format:d-m-Y',
                'amount' => 'sometimes|required|integer|min:1',
            ], [
                'date.required' => 'Tanggal tidak boleh kosong.',
                'date.date_format' => 'Format tanggal harus dd-mm-yyyy.',
                'amount.required' => 'Jumlah dana tidak boleh kosong.',
                'amount.integer' => 'Jumlah dana harus berupa angka.',
                'amount.min' => 'Jumlah dana minimal 1.',
            ]);

            // Update hanya jika key-nya ada
            if (array_key_exists('date', $validated)) {
                $tx->date = \Carbon\Carbon::createFromFormat('d-m-Y', $validated['date']);
            }
            if (array_key_exists('amount', $validated)) {
                $tx->amount = $validated['amount'];
            }

            $tx->save();

            return response()->json([
                'message' => 'Data dana masuk berhasil diperbarui',
                'data' => $tx,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error during fund transaction update: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui dana masuk',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Hapus hanya FundTransaction bertipe "in"
     */
    public function destroy($id)
    {
        try {
            $tx = FundTransaction::findOrFail($id);

            if ($tx->type !== 'in') {
                return response()->json([
                    'message' => 'Hanya transaksi dana masuk yang bisa dihapus'
                ], 403);
            }

            $tx->delete();

            return response()->json([
                'message' => 'Data dana masuk berhasil dihapus',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => "Transaksi dengan ID $id tidak ditemukan."
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error during fund transaction deletion: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json([
                'message' => 'Terjadi kesalahan saat menghapus data dana masuk',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
