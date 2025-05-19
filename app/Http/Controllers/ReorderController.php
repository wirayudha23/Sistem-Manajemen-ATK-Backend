<?php

namespace App\Http\Controllers;

use App\Models\Reorder;
use App\Models\ReorderCart;
use App\Models\ReorderDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Helpers\PhoneHelper;
use App\Services\WhatsAppService;

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
        $validator = Validator::make($request->all(), [
            'delivery_date' => 'required|date_format:d-m-Y|after_or_equal:' . $now->format('d-m-Y'),
            'phone_number'  => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>'error','message'=>$validator->errors()->first()],400);
        }

        $cart = ReorderCart::all();
        if ($cart->isEmpty()) {
            return response()->json(['status'=>'error','message'=>'Cart is empty.'],400);
        }

        $delivery = Carbon::createFromFormat('d-m-Y', $request->delivery_date)->format('Y-m-d');
        $reorder = Reorder::create(['reorder_date'=>$now,'delivery_date'=>$delivery]);
        foreach ($cart as $item) {
            ReorderDetail::create([
                'reorder_id'=>$reorder->id,
                'product_id'=>$item->product_id,
                'reorder_quantity'=>$item->reorder_quantity,
            ]);
        }
        $reorder->updateTotalPrice();
        ReorderCart::truncate();

        $to      = $this->wa->formatPhone($request->phone_number);
        $message = $this->wa->buildReorderMessage($reorder);

        try {
            $this->wa->sendMessage($to, $message);
        } catch (\Exception $e) {
            \Log::error('[WA ERROR] '.$e->getMessage());
        }

        return response()->json([
            'status'=>'success',
            'message'=>'Reorder created and WhatsApp sent.',
            'data'=>$reorder
        ],201);
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
        $reorder = Reorder::with('items.product')->find($id);

        if (!$reorder) {
            return response()->json([
                'status' => 'error',
                'message' => 'Reorder not found.',
            ], 404);
        }

        $reorderDate = Carbon::parse($reorder->reorder_date);

        $validator = Validator::make($request->all(), [
            'delivery_date' => 'required|date_format:d-m-Y|after_or_equal:' . $reorderDate->format('d-m-Y'),
            'details' => 'required|array',
            'details.*.product_id' => 'required|exists:products,id',
            'details.*.reorder_quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 400);
        }

        $deliveryDate = Carbon::createFromFormat('d-m-Y', $request->delivery_date)->format('Y-m-d');

        // Update delivery_date
        $reorder->update([
            'delivery_date' => $deliveryDate
        ]);

        // Update reorder quantity
        foreach ($request->details as $detail) {
            $reorderDetail = $reorder->items->where('product_id', $detail['product_id'])->first();

            if ($reorderDetail) {
                $reorderDetail->update([
                    'reorder_quantity' => $detail['reorder_quantity'],
                ]);
            } else {
                ReorderDetail::create([
                    'reorder_id' => $reorder->id,
                    'product_id' => $detail['product_id'],
                    'reorder_quantity' => $detail['reorder_quantity'],
                ]);
            }
        }

        $reorder->updateTotalPrice();

        $reorder->fresh();

        return response()->json([
            'status' => 'success',
            'message' => 'Reorder updated.',
            'data' => $reorder,
        ]);
    }

    public function destroy($id)
    {
        $reorder = Reorder::find($id);

        if (!$reorder) {
            return response()->json([
                'status' => 'error',
                'message' => 'Reorder not found.',
            ], 404);
        }

        $reorder->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Reorder deleted.',
        ]);
    }
}
