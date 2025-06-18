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
        $reorder = Reorder::where('id', $reorderId)->first();
        if (!$reorder) {
            return response()->json([
                'status' => 'error',
                'message' => "Reorder dengan ID {$reorderId} tidak ditemukan."
            ], 404);
        }

        if ($reorder->whatsapp_status === 'gagal_dikirim') {
            return response()->json([
                'status' => 'error',
                'message' => 'Reorder ini gagal dikirim sebelumnya. Silakan coba lagi.'
            ], 400);
        }

        if ($reorder->whatsapp_status === 'sudah_dikirim') {
            return response()->json([
                'status' => 'error',
                'message' => 'Pesan WA sudah dikirim sebelumnya untuk data pengadaan ulang ini.'
            ], 400);
        }

        if ($reorder->whatsapp_status === 'update_belum_dikirim') {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada update WA yang perlu dikirim.'
            ], 400);
        }

        if ($reorder->whatsapp_status === 'update_sudah_dikirim') {
            return response()->json([
                'status' => 'error',
                'message' => 'Update WA sudah dikirim sebelumnya.'
            ], 400);
        }

        if ($reorder->whatsapp_status === 'update_gagal_dikirim') {
            return response()->json([
                'status' => 'error',
                'message' => 'Update WA gagal dikirim sebelumnya.'
            ], 400);
        }

        if ($reorder->whatsapp_status === 'dibatalkan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Reorder ini sudah dibatalkan dan tidak dapat dikirim ulang.'
            ], 400);
        }

        if (
            $reorder->reorder_status === [
                'proses',
                'selesai',
                'dibatalkan'
            ]
        ) {
            return response()->json([
                'status' => 'error',
                'message' => 'Reorder ini sudah dalam proses atau selesai dan tidak dapat dikirim ulang.'
            ], 400);
        }

        // Validasi input user_id
        try {
            $request->validate([
                'user_id' => ['required', 'exists:users,id'],
            ], [
                'user_id.required' => 'Tolong pilih User yang akan menerima pesan WA.',
                'user_id.exists' => 'Tolong pilih User yang valid dari daftar yang ada.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        }

        $user = User::find($request->input('user_id'));
        Log::info("[WA SEND] User ditemukan: {$user->name} dengan posisi {$user->position}");

        if ($user->position !== 'Rumah Tangga') {
            Log::info("[WA SEND] User bukan Rumah Tangga: {$user->position}");
            return response()->json([
                'status' => 'error',
                'message' => 'User terpilih tidak berposisi Rumah Tangga.'
            ], 400);
        }

        try {
            // $user = $reorder->user;
            $to = $this->wa->formatPhone($user->phone_number);
            $message = $this->wa->buildReorderMessage($reorder);
            Log::info("[WA SEND] Kirim WA ke: $to\nPesan: $message");

            $this->wa->sendMessage($to, $message);

            $reorder->update([
                'whatsapp_status' => 'sudah_dikirim',
                'reorder_status' => 'proses',
                'sent_at' => Carbon::now('Asia/Jakarta'),
                'user_id' => $user->id,
                'wa_error_message' => null,
            ]);

            Log::info("[WA SEND] WA berhasil dikirim dan status reorder diperbarui.");
            return response()->json([
                'status' => 'success',
                'message' => 'WA berhasil dikirim ke ' . $user->name,
                'data' => $reorder
            ], 200);

        } catch (\Exception $e) {
            Log::error("[WA FAILED] Reorder {$reorder->id} | {$e->getMessage()}");

            $reorder->update([
                'whatsapp_status' => 'gagal_dikirim',
                'wa_error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengirim WA: ' . $e->getMessage(),
                'data' => $reorder
            ], 500);
        }
    }


    /**
     * Batalkan reorder: jika sudah dikirim, kirim pesan pembatalan; jika belum, cukup ubah status.
     */
    public function cancel($reorderId)
    {
        // Ambil data Reorder
        $reorder = Reorder::where('id', $reorderId)->first();
        if (!$reorder) {
            return response()->json([
                'status' => 'error',
                'message' => "Reorder dengan ID {$reorderId} tidak ditemukan."
            ], 404);
        }

        if ($reorder->whatsapp_status === 'belum_dikirim') {
            return response()->json([
                'status' => 'error',
                'message' => "Reorder dengan status '{$reorder->whatsapp_status}' tidak dapat dibatalkan.",
            ], 400);
        }

        if ($reorder->whatsapp_status === 'dibatalkan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Reorder ini sudah dibatalkan sebelumnya.'
            ], 400);
        }

        if ($reorder->reorder_status === 'dibatalkan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Reorder ini sudah dibatalkan sebelumnya.'
            ], 400);
        }

        if ($reorder->reorder_status === 'selesai') {
            return response()->json([
                'status' => 'error',
                'message' => 'Reorder ini sudah selesai dan tidak dapat dibatalkan.'
            ], 400);
        }

        try {
            $user = $reorder->user;
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User tidak ditemukan pada reorder ini.',
                ], 400);
            }
            $to = $this->wa->formatPhone($user->phone_number);
            $message = $this->wa->buildCancelMessage($reorder);
            $this->wa->sendMessage($to, $message);

            $reorder->update([
                'whatsapp_status' => 'dibatalkan',
                'reorder_status' => 'dibatalkan',
                'cancelled_at' => Carbon::now('Asia/Jakarta'),
                'wa_error_message' => null,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Pesan pembatalan WA berhasil dikirim ke ' . $user->name,
                'data' => $reorder,
            ], 200);

        } catch (\Exception $e) {
            $reorder->update(['wa_error_message' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengirim notifikasi pembatalan: ' . $e->getMessage(),
                'data' => $reorder,
            ], 500);
        }
    }

    public function sendUpdate($reorderId)
    {
        $reorder = Reorder::where('id', $reorderId)->first();
        if (!$reorder) {
            Log::warning("[WA UPDATE] Reorder tidak ditemukan: $reorderId");
            return response()->json([
                'status' => 'error',
                'message' => "Reorder dengan ID $reorderId tidak ditemukan."
            ], 404);
        }

        if ($reorder->whatsapp_status !== 'update_belum_dikirim') {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada pembaruan WA yang perlu dikirim.',
            ], 400);
        }

        $diff = $reorder->pending_update_diff;
        if (empty($diff)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data diff tidak tersedia.',
            ], 400);
        }

        try {
            $to = $this->wa->formatPhone($reorder->user->phone_number);

            // Hitung sisa qty setelah update
            $remaining = $reorder->items()->sum('reorder_quantity');

            if ($remaining === 0) {
                // semua qty benar-benar jadi 0 → cancel
                $message = $this->wa->buildCancelMessage($reorder);
                $reorder->update([
                    'whatsapp_status' => 'update_sudah_dikirim',
                    'reorder_status' => 'dibatalkan',
                    'pending_update_diff' => null,
                    'wa_error_message' => null,
                ]);
                $responseMsg = 'Pembatalan WA berhasil dikirim.';
            } else {
                // masih ada qty positif di stock → update
                $message = $this->wa->buildUpdateMessage($reorder, $diff);
                $reorder->update([
                    'whatsapp_status' => 'update_sudah_dikirim',
                    'pending_update_diff' => null,
                    'wa_error_message' => null,
                ]);
                $responseMsg = 'Pembaruan WA berhasil dikirim.';
            }

            // Kirim pesan ke WA
            $this->wa->sendMessage($to, $message);

            Log::info("[WA UPDATE] Update WA berhasil untuk Reorder ID: $reorderId");
            return response()->json([
                'status' => 'success',
                'message' => $responseMsg,
                'data' => $reorder,
            ], 200);

        } catch (\Exception $e) {
            $reorder->update([
                'whatsapp_status' => 'update_gagal_dikirim',
                'wa_error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengirim pembaruan: ' . $e->getMessage(),
                'data' => $reorder,
            ], 500);
        }
    }
}

