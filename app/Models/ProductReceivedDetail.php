<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReceivedDetail extends Model
{
    use HasFactory;

    protected $casts = [
        'received_date' => 'date:d-m-Y',
    ];

    protected $fillable = [
        'product_received_id',
        'product_id',
        'received_quantity',
        'price',
        'total_product_price',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function productReceived()
    {
        return $this->belongsTo(ProductReceived::class, 'product_received_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
