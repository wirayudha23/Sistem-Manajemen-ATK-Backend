<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FundTransaction extends Model
{
    use HasUuids;

    protected $table = 'fund_transactions';

    protected $fillable = [
        'id',
        'date',
        'type',
        'amount',
        'product_received_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function productReceived()
    {
        return $this->belongsTo(ProductReceived::class, 'product_received_id');
    }

    public static function monthlyBalance(int $year, int $month): int
    {
        return static::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->selectRaw("
            COALESCE(SUM(CASE WHEN type='in'  THEN amount ELSE 0 END),0)
              - COALESCE(SUM(CASE WHEN type='out' THEN amount ELSE 0 END),0)
                AS balance
            ")
            ->value('balance');
    }
}
