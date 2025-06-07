<?php

namespace App\Http\Controllers;

use App\Models\Reorder;
use App\Models\User;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReorderWhatsappController extends Controller
{
    protected WhatsAppService $wa;

    public function __construct(WhatsAppService $wa)
    {
        $this->wa = $wa;
    }

    public function send(Request $request, $reorderId)
    {
        // 1. Ambil Reorder dengan find() (atau first())
        $reorder = Reorder::where('id', $reorderId)->first();
        if (!$reorder) {
            return response()->json([
                'status' => 'error',
                'message' => "Reorder dengan ID {$reorderId} tidak ditemukan."
            ], 404);
        }

        // 2. Cek agar tidak kirim ganda jika sudah 'sent'
        if ($reorder->status === 'sent') {
            return response()->json([
                'status' => 'error',
                'message' => 'Pesan WA sudah dikirim sebelumnya untuk Reorder ini.'
            ], 400);
        }

        // 3. Validasi input user_id (harus ada di tabel users)
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        // 4. Ambil User dan pastikan posisinya 'Rumah Tangga'
        $user = User::find($request->input('user_id'));
        if ($user->position !== 'Rumah Tangga') {
            return response()->json([
                'status' => 'error',
                'message' => 'User terpilih tidak berposisi Rumah Tangga.'
            ], 400);
        }

        // 5. Format nomor, build message, kirim WA
        try {
            // 5A. Format nomor telepon (0812345xxx → 62812345xxx)
            $to = $this->wa->formatPhone($user->phone_number);

            // 5B. Build pesan (memerlukan satu instance Reorder, bukan Collection)
            $message = $this->wa->buildReorderMessage($reorder);

            // 5C. Kirim WhatsApp
            $this->wa->sendMessage($to, $message);

            // 6. Jika sukses → update status menjadi 'sent'
            $reorder->update([
                'status' => 'sent',
                'sent_at' => Carbon::now('Asia/Jakarta'),
                'user_id' => $user->id,
                'wa_error_message' => null,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'WA berhasil dikirim ke ' . $user->name,
                'data' => $reorder
            ], 200);

        } catch (\Exception $e) {
            // 7. Jika gagal → update status menjadi 'failed' dan simpan error message
            Log::error("[WA FAILED] Reorder {$reorder->id} | {$e->getMessage()}");
            $reorder->update([
                'status' => 'failed',
                'wa_error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengirim WA: ' . $e->getMessage(),
                'data' => $reorder
            ], 500);
        }
    }
}

