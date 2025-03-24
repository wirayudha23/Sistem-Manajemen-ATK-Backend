<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReceivedDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'product_received_id',
        'product_id',
        'received_quantity',
        'price',
        'total_product_price',
    ];

    public function productReceived()
    {
        return $this->belongsTo(ProductReceived::class, 'product_received_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
