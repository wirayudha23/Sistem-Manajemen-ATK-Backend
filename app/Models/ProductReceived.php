<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\ProductReceivedDetail;

class ProductReceived extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'reorder_id',
        'received_date',
        'total_received_price',
        'received_status',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });

        // static::created(function ($productReceived) {
        //     foreach ($productReceived->details as $detail) {
        //         $detail->product->increment('stock', $detail->received_quantity);
        //     }
        // });
    }

    public function details()
    {
        return $this->hasMany(ProductReceivedDetail::class, 'product_received_id');
    }

    public function reorder()
    {
        return $this->belongsTo(Reorder::class);
    }

    public function updateTotalPrice()
    {
        $total = $this->details->sum('total_product_price');
        $this->update(['total_received_price' => $total]);
    }
}
