<?php

namespace App\Http\Controllers;

use App\Models\Reorder;
use App\Models\ReorderCart;
use App\Models\ReorderDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;

class ReorderController extends Controller
{
    protected WhatsAppService $wa;

    public function __construct(WhatsAppService $wa)
    {
        $this->wa = $wa;
    }

    public function index(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);
            $sort_column = $request->get('sort_column', 'reorder_date');
            $sort_type = $request->get('sort_type', 'desc');
            $search = $request->get('search', '');
            $search_column = $request->get('search_column', '');

            $query = Reorder::query()->with('items.product');

            if ($search_column && $search) {
                $query->where($search_column, 'like', '%' . $search . '%');
            }

            $reorders = $query
                ->orderBy($sort_column, $sort_type)
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'message' => 'Reorders fetched successfully',
                'data' => $reorders->load('items.product'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching reorders: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'error' => $e->getMessage(), // Tambahkan pesan error untuk debugging
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $now = Carbon::now('Asia/Jakarta');

        // 1. Validasi input
        $validator = Validator::make($request->all(), [
            'delivery_date' => 'required|date_format:d-m-Y|after_or_equal:' . $now->format('d-m-Y'),
        ], [
            'delivery_date.required' => 'Tanggal pengiriman tidak boleh kosong.',
            'delivery_date.date_format' => 'Format tanggal pengiriman harus dd-mm-yyyy.',
            'delivery_date.after_or_equal' => 'Tanggal pengiriman harus hari ini atau setelahnya.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $cart = ReorderCart::with('product')->get();

        if ($cart->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Keranjang pengadaan ulang kosong. Silakan tambahkan item terlebih dahulu.',
            ], 400);
        }

        $invalid = $cart->filter(fn($item) => $item->reorder_quantity < 1);
        if ($invalid->isNotEmpty()) {
            $names = $invalid->pluck('product.name')->all();
            return response()->json([
                'status' => 'error',
                'message' => 'Beberapa item memiliki jumlah kurang dari 1',
                'products' => $names,
            ], 400);
        }

        try {
            // 2. Jalankan semua operasi DB di dalam satu DB::transaction(…)
            $reorder = DB::transaction(function () use ($request, $now) {
                // 2a. Format tanggal delivery
                $delivery = Carbon::createFromFormat('d-m-Y', $request->delivery_date)
                    ->format('Y-m-d');

                // 2b. Buat record Reorder (status default 'pending')
                $reorder = Reorder::create([
                    'reorder_date' => $now,
                    'delivery_date' => $delivery,
                    'total_reorder_price' => 0,      // nanti di‐update
                    'whatsapp_status' => 'belum_dikirim',
                    'reorder_status' => 'draft',
                    'wa_error_message' => null,
                    'sent_at' => null,
                ]);

                // 2c. Buat masing‐masing ReorderDetail
                foreach (ReorderCart::all() as $item) {
                    ReorderDetail::create([
                        'reorder_id' => $reorder->id,
                        'product_id' => $item->product_id,
                        'reorder_quantity' => $item->reorder_quantity,
                    ]);
                }

                // 2d. Hitung total price dan simpan
                $reorder->updateTotalPrice();

                // 2e. Kosongkan cart

                return $reorder;
            });

            ReorderCart::truncate();

            // 3. Jika semua di dalam closure berhasil, transaksi otomatis commit
            return response()->json([
                'status' => 'success',
                'message' => 'Data Pengadaan ulang berhasil dibuat.',
                'data' => $reorder,
            ], 201);

        } catch (\Exception $e) {
            // 4. Jika terjadi exception apa pun di dalam closure, Laravel otomatis rollback
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan data pengadaan ulang: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $reorder = Reorder::with('items.product')->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Reorder fetched successfully',
                'data' => $reorder->load('items.product'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Reorder not found.',
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $now = Carbon::now('Asia/Jakarta');

        // 1. Ambil data & cek existence
        $reorder = Reorder::with('items.product')->find($id);
        if (!$reorder) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data reorder tidak ditemukan.'
            ], 404);
        }

        if ($reorder->receivings()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data pengadaan ulang tidak bisa diupdate karena sudah diterima.'
            ], 400);
        }

        if ($reorder->whatsapp_status === 'dibatalkan') {
            return response()->json([
                'status' => 'error',
                'message' => 'Reorder sudah dibatalkan dan tidak bisa diupdate.'
            ], 400);
        }

        if (in_array($reorder->reorder_status, ['selesai', 'dibatalkan'])) {
            return response()->json([
                'status' => 'error',
                'message' => "Reorder sudah {$reorder->reorder_status} dan tidak bisa diupdate."
            ], 400);
        }

        // Snapshot lama untuk diff
        $old = [
            'delivery_date' => $reorder->delivery_date->format('Y-m-d'),
            'total_reorder_price' => $reorder->total_reorder_price,
            'items' => $reorder->items
                ->map(fn($i) => ['product_id' => $i->product_id, 'qty' => $i->reorder_quantity])
                ->keyBy('product_id')
                ->toArray(),
        ];

        // 3. Validasi input
        $reorderDateStr = $reorder->reorder_date->format('d-m-Y');
        $validator = Validator::make($request->all(), [
            'delivery_date' => 'required|date_format:d-m-Y|after_or_equal:' . $reorderDateStr,
            'details' => 'sometimes|array',
            'details.*.product_id' => 'required_with:details|exists:products,id',
            'details.*.reorder_quantity' => 'required_with:details|integer|min:1',
        ], [
            'delivery_date.required' => 'Tanggal pengiriman harus diisi.',
            'delivery_date.after_or_equal' => 'Tanggal pengiriman tidak boleh lebih kecil dari tanggal permintaan.',
            'details.array' => 'Detail harus berupa array.',
            'details.*.product_id.required_with' => 'ID produk harus diisi.',
            'details.*.reorder_quantity.required_with' => 'Jumlah pengadaan harus diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // 4a. Handle cancel via empty details for draft
        \Log::info('📝 Isi details saat update reorder:', $request->details);
        \Log::info('✅ Jumlah details: ' . (is_array($request->details) ? count($request->details) : 'bukan array'));

        if (
            $request->has('details')
            && is_array($request->details)
            && collect($request->details)->filter(fn($d) => !empty($d))->isEmpty()
            && $reorder->reorder_status === 'draft'
            && $reorder->whatsapp_status === 'belum_dikirim'
        ) {
            // Compute diff: all items to zero
            $diffItems = $reorder->items->mapWithKeys(fn($item) => [
                $item->product_id => [
                    'from' => $item->reorder_quantity,
                    'to' => 0,
                    'name' => $item->product->name,
                ],
            ])->toArray();

            foreach ($reorder->items as $item) {
                $item->update(['reorder_quantity' => 0]);
            }

            $reorder->update([
                'reorder_status' => 'dibatalkan',
                'whatsapp_status' => 'dibatalkan',
                'pending_update_diff' => ['items' => $diffItems],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Pengadaan dibatalkan',
                'data' => $reorder,
            ], 200);
        }

        // 4b. Handle cancel via empty details for non-draft (after sent)
        if (
            $request->has('details')
            && is_array($request->details)
            && collect($request->details)->filter(fn($d) => !empty($d))->isEmpty()
            && $reorder->reorder_status !== 'draft'
            && $reorder->whatsapp_status !== 'dibatalkan'
        ) {
            // Compute diff: all items to zero
            $diffItems = $reorder->items->mapWithKeys(fn($item) => [
                $item->product_id => [
                    'from' => $item->reorder_quantity,
                    'to' => 0,
                    'name' => $item->product->name,
                ],
            ])->toArray();

            foreach ($reorder->items as $item) {
                $item->update(['reorder_quantity' => 0]);
            }

            $reorder->update([
                'reorder_status' => 'dibatalkan',
                'whatsapp_status' => 'update_belum_dikirim',
                'pending_update_diff' => ['items' => $diffItems],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Reorder dibatalkan setelah update dikirim sebelumnya.',
                'data' => $reorder,
            ], 200);
        }

        // 5. Update normal dalam transaksi
        try {
            $updated = DB::transaction(function () use ($request, $reorder) {
                // 5a. Update delivery_date
                $newDelivery = Carbon::createFromFormat('d-m-Y', $request->delivery_date)->format('Y-m-d');
                $reorder->update(['delivery_date' => $newDelivery]);

                // 5b. Sync details jika ada
                if ($request->has('details')) {
                    $incoming = collect($request->details)
                        ->pluck('reorder_quantity', 'product_id')
                        ->toArray();
                    // delete removed
                    $reorder->items()->whereNotIn('product_id', array_keys($incoming))->delete();
                    // update existing & add new
                    foreach ($incoming as $pid => $qty) {
                        $item = $reorder->items()->firstWhere('product_id', $pid);
                        if ($item) {
                            $item->update(['reorder_quantity' => $qty]);
                        } else {
                            $reorder->items()->create(['product_id' => $pid, 'reorder_quantity' => $qty]);
                        }
                    }
                }

                // 5c. Recompute total
                $reorder->updateTotalPrice();

                return $reorder->fresh('items');
            });

            // 6. Snapshot baru & compute diff including removed items
            $new = [
                'delivery_date' => $updated->delivery_date->format('Y-m-d'),
                'total_reorder_price' => $updated->total_reorder_price,
                'items' => $updated->items
                    ->map(fn($i) => ['product_id' => $i->product_id, 'qty' => $i->reorder_quantity])
                    ->keyBy('product_id')
                    ->toArray(),
            ];

            $diff = [];
            // Date diff
            if ($old['delivery_date'] !== $new['delivery_date']) {
                $diff['delivery_date'] = ['from' => $old['delivery_date'], 'to' => $new['delivery_date']];
            }
            // Price diff
            if ($old['total_reorder_price'] !== $new['total_reorder_price']) {
                $diff['total_reorder_price'] = ['from' => $old['total_reorder_price'], 'to' => $new['total_reorder_price']];
            }
            // Items diff
            foreach ($old['items'] as $pid => $o) {
                $nQty = $new['items'][$pid]['qty'] ?? 0;
                if ($o['qty'] !== $nQty) {
                    $diff['items'][$pid] = [
                        'name' => $reorder->items->firstWhere('product_id', $pid)?->product->name ?? null,
                        'from' => $o['qty'],
                        'to' => $nQty,
                    ];
                }
            }

            $origWa = $reorder->getOriginal('whatsapp_status');
            $origStatus = $reorder->getOriginal('reorder_status');

            // 7. Simpan diff jika ada perubahan dan bukan status dibatalkan
            if (
                !empty($diff)
                && $origWa !== 'dibatalkan'
                && !($origWa === 'belum_dikirim' && $origStatus === 'draft')
            ) {
                $updated->update([
                    'pending_update_diff' => $diff,
                    'whatsapp_status' => 'update_belum_dikirim',
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Reorder berhasil diperbarui.',
                'data' => $updated,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui reorder: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function destroy($id)
    {
        $reorder = Reorder::find($id);

        if (!$reorder) {
            return response()->json([
                'status' => 'error',
                'message' => 'Reorder tidak ditemukan.',
            ], 404);
        }

        if ($reorder->receivings()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data pengadaan ulang tidak bisa diupdate karena sudah diterima.'
            ], 400);
        }

        $ws = $reorder->whatsapp_status;
        $rs = $reorder->reorder_status;

        $bolehHapus =
            ($ws === 'belum_dikirim' && $rs === 'draft') ||
            ($ws === 'dibatalkan' && $rs === 'dibatalkan') ||
            ($ws === 'update_sudah_dikirim' && $rs === 'dibatalkan');

        if (!$bolehHapus) {
            return response()->json([
                'status' => 'error',
                'message' => 'Reorder hanya dapat dihapus jika status WhatsApp dan status penerimaan sesuai ketentuan.',
            ], 400);
        }

        $reorder->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Reorder berhasil dihapus.',
        ]);
    }
}
