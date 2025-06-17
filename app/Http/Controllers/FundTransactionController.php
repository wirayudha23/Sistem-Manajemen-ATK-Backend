<?php

namespace App\Http\Controllers;

use App\Models\FundTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FundTransactionController extends Controller
{
    public function index(Request $request)
    {
        $req = $request->validate([
            'year'  => 'required|digits:4|integer',
            'month' => 'required|digits_between:1,2|integer|min:1|max:12',
        ]);

        $balance = FundTransaction::monthlyBalance($req['year'], $req['month']);
        $in = FundTransaction::where ('type', 'in')
            ->whereYear('date', $req['year'])
            ->whereMonth('date', $req['month'])
            ->sum('amount');

        $out = FundTransaction::where ('type', 'out')
            ->whereYear('date', $req['year'])
            ->whereMonth('date', $req['month'])
            ->sum('amount');

        return response()->json([
            'status'   => 'success',
            'message'  => 'Data transaksi dana berhasil diambil.',
            'year'    => $req['year'],
            'month'   => $req['month'],
            'in'      => $in,
            'out'     => $out,
            'balance' => $balance,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'date'   => 'required|date_format:d-m-Y',
                'amount' => 'required|integer|min:1',
            ],
            [
                'date.required'   => 'Tanggal tidak boleh kosong.',
                'date.date_format' => 'Format tanggal harus dd-mm-yyyy.',
                'amount.required' => 'Jumlah dana tidak boleh kosong.',
                'amount.integer'  => 'Jumlah dana harus berupa angka.',
                'amount.min'      => 'Jumlah dana minimal 1.',
            ]);

            $tx = FundTransaction::create([
                'id'                    => (string) Str::uuid(),
                // 'date'                  => \Carbon\Carbon::createFromFormat('d-m-Y', $validated['date']),
                'date'                  => now(),
                'type'                  => 'in',
                'amount'                => $validated['amount'],
                'product_received_id'   => null,
            ]);

            return response()->json([
                'message' => 'Dana masuk berhasil dicatat.',
                'data'    => $tx,
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Error during fund transaction creation: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan saat mencatat dana masuk.',
                'error'   => $e->getMessage(), // Opsional, untuk debugging
            ], 500);
        }
    }
}
