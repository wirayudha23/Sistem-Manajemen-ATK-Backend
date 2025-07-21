<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Log;

class Product extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'price',
        'stock',
        'image',
        'category_id',
        'unit_id',
        'economic_order_quantity',
        'safety_stock',
        'reorder_point',
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = Str::title(trim($value));
    }

    // protected $casts = [
    //     'economic_order_quantity' => 'decimal:2',
    //     'safety_stock' => 'decimal:2',
    //     'reorder_point' => 'decimal:2',
    // ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()

    {
        return $this->belongsTo(Unit::class);
    }

    public function checkoutDetails()
    {
        return $this->hasMany(CheckoutDetail::class);
    }

    /**
     * Ambil data total checkout per bulan untuk 12 bulan penuh terakhir,
     * keyed by month number (1–12), dan default 0 bila tidak ada data.
     */
    protected function getRollingCheckoutData(): array
    {
        $end = Carbon::now()->startOfMonth();       // 1st of current month
        $start = $end->copy()->subMonths(12);         // 12 months ago

        $rows = $this->checkoutDetails()
            ->selectRaw('MONTH(checkouts.checkout_date) as month, SUM(checkout_quantity) as total')
            ->join('checkouts', 'checkout_details.checkout_id', '=', 'checkouts.id')
            ->whereBetween('checkouts.checkout_date', [$start, $end->subDay()])
            ->groupByRaw('MONTH(checkouts.checkout_date)')
            ->pluck('total', 'month')
            ->toArray();

        return $rows;
    }

    /**
     * Hitung & simpan Safety Stock dan ROP (rolling 12 months).
     */
    public function calculateSafetyAndRop(): void
    {
        $LT = 2;  // lead time in months
        $data = $this->getRollingCheckoutData();

        // Pastikan array 1..12, default 0
        $months = collect(range(1, 12))
            ->map(fn($m) => data_get($data, $m, 0));

        $avgMonthly = $months->sum() / 12;
        $maxMonthly = $months->max();

        $safetyRaw = ($maxMonthly - $avgMonthly) * $LT;
        $ropRaw = ($avgMonthly * $LT) + $safetyRaw;

        $safetyStock = ceil($safetyRaw);
        $reorderPoint = ceil($ropRaw);

        Log::info("Monthly metrics for {$this->id}", [
            'avgMonthly' => $avgMonthly,
            'maxMonthly' => $maxMonthly,
            'safetyRaw' => $safetyRaw,
            'safetyStock' => $safetyStock,
            'ropRaw' => $ropRaw,
            'reorderPoint' => $reorderPoint,
        ]);

        $this->update([
            'safety_stock' => $safetyStock,
            'reorder_point' => $reorderPoint,
        ]);
    }

    /**
     * Hitung & simpan EOQ (rolling 12 months, run yearly).
     */
    public function calculateEoq(): void
    {
        $S = 5000;               // setup cost
        $H = $this->price * 0.10; // holding cost per unit per year
        $data = $this->getRollingCheckoutData();
        $D = array_sum($data);   // total demand 12 months

        $eoqRaw = $D > 0 ? sqrt((2 * $D * $S) / $H) : 0;
        $eoq = ceil($eoqRaw);

        Log::info("Yearly EOQ for {$this->id}", [
            'D' => $D,
            'rawEOQ' => $eoqRaw,
            'EOQ' => $eoq
        ]);

        $this->update([
            'economic_order_quantity' => $eoq,
        ]);
    }
}
