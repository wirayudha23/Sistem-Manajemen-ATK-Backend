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

    public function getYearlyTotalAttribute()
    {
        $year = Carbon::now()->subYear()->year;

        return $this->checkoutDetails()
            ->whereHas('checkout', function ($query) use ($year) {
                $query->whereYear('checkout_date', $year);
            })
            ->sum('checkout_quantity');
    }

    public function getMonthlyMaxAttribute()
    {
        $year = Carbon::now()->subYear()->year;

        $monthlyTotals = $this->checkoutDetails()
            ->select(DB::raw('MONTH(checkouts.checkout_date) as month, SUM(checkout_quantity) as total'))
            ->join('checkouts', 'checkout_details.checkout_id', '=', 'checkouts.id')
            ->whereYear('checkouts.checkout_date', $year)
            ->groupBy(DB::raw('MONTH(checkouts.checkout_date)'))
            ->pluck('total')
            ->toArray();

        return $monthlyTotals ? max($monthlyTotals) : 0;
    }

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function calculateInventoryMetrics()
    {
        $S = 5000;
        $LT = 2;

        $D = $this->yearlyTotal;
        $H = $this->price * 0.10;

        // $eoq = $D > 0 ? sqrt((2 * $D * $S) / $H) : 0;
        $eoq = sqrt((2 * $D * $S) / $H);

        $averageMonthly = $D >0 ? ($D / 12) : 0;
        // $safetyStock = max(0, $this->monthlyMax - $averageMonthly) * $LT;
        $safetyStock = ($this->monthlyMax - $averageMonthly) * $LT;

        $rop = ($LT * $averageMonthly) + $safetyStock;

        Log::info('Calculating inventory metrics for product ' . $this->id, [
            'D' => $D,
            'H' => $H,
            'averageMonthly' => $averageMonthly,
            'maxMonthly' => $this->monthlyMax,
            'eoq' => $eoq,
            'safetyStock' => $safetyStock,
            'rop' => $rop
        ]);

        $this->update([
            'economic_order_quantity' => $eoq,
            'safety_stock' => $safetyStock,
            'reorder_point' => $rop
        ]);
    }
}
