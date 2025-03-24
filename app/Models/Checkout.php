<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Checkout extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'checkout_date',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    public function items()
    {
        return $this->hasMany(CheckoutDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
