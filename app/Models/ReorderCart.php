<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReorderCart extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'reorder_quantity',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($cart) {
            $cart->id = (string) Str::uuid();
        });
    }
}
