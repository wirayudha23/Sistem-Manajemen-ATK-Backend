<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Checkout;

class Purpose extends Model
{
    use HasFactory;
    protected $fillable = [
        'name'
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = Str::title($value);
    }

    public function checkouts()
    {
        return $this->hasMany(Checkout::class);
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
}
