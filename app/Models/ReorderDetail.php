<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReorderDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'reorder_id',
        'product_id',
        'reorder_quantity',
        'original_price',
    ];

    public function reorder()
    {
        return $this->belongsTo(Reorder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();

            // Jika original_price belum diisi, ambil dari harga produk saat itu
            if (!$model->original_price) {
                $product = Product::find($model->product_id);
                if ($product) {
                    $model->original_price = $product->price;
                }
            }
            // Hitung total harga untuk produk tersebut
            $model->total_product_price = $model->original_price * $model->reorder_quantity;
        });

        static::updating(function ($model) {
            // Perbarui total harga produk saat reorder_quantity diubah
            $model->total_product_price = $model->original_price * $model->reorder_quantity;
        });

        // Setelah menyimpan detail, update total harga di Reorder
        static::saved(function ($model) {
            if ($model->reorder) {
                $model->reorder->updateTotalPrice();
            }
        });

        static::deleted(function ($model) {
            if ($model->reorder) {
                $model->reorder->updateTotalPrice();
            }
        });
    }
}
