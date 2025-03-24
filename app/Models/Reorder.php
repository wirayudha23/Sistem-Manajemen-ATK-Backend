<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reorder extends Model
{
    use HasFactory;

    protected $fillable = [
        'reorder_date',
        'delivery_date',
        'total_reorder_price',
    ];

    public function items()
    {
        return $this->hasMany(ReorderDetail::class)->with('product');
    }

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    public function updateTotalPrice()
    {
        $total = $this->items()->sum('total_product_price');
        $this->update(['total_reorder_price' => $total]);
    }
}
