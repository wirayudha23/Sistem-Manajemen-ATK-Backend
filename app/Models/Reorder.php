<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reorder extends Model
{
    use HasFactory;

    protected $casts = [
        'reorder_date' => 'datetime:Y-m-d H:i:s',
        'delivery_date' => 'date',
        'sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'pending_update_diff' => 'array',
    ];

    protected $fillable = [
        'month_sequence',
        'reorder_code',
        'reorder_date',
        'delivery_date',
        'total_reorder_price',
        'whatsapp_status',
        'reorder_status',
        'sent_at',
        'user_id',
        'cancelled_at',
        'wa_error_message',
        'pending_update_diff',
    ];

    public function items()
    {
        return $this->hasMany(ReorderDetail::class)->with('product');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isWaPending()
    {
        return $this->whatsapp_status === 'belum_dikirim';
    }

    public function isWaSent()
    {
        return $this->whatsapp_status === 'sudah_dikirim';
    }

    public function isWaFailed()
    {
        return $this->whatsapp_status === 'gagal_dikirim';
    }

    public function isCompletedWa()
    {
        return $this->whatsapp_status === 'selesai';
    }

    public function isWaCancelled()
    {
        return $this->whatsapp_status === 'dibatalkan';
    }

    public function isDraft()
    {
        return $this->reorder_status === 'draft';
    }
    public function isInProgress()
    {
        return $this->reorder_status === 'proses';
    }
    public function isCompleted()
    {
        return $this->reorder_status === 'selesai';
    }
    public function isCancelled()
    {
        return $this->reorder_status === 'dibatalkan';
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

    protected static function booted()
    {
        static::creating(function ($model) {
            // Generate UUID
            $model->id = (string) \Illuminate\Support\Str::uuid();

            // Hitung sequence bulan ini
            $year = now('Asia/Jakarta')->year;
            $month = now('Asia/Jakarta')->month;
            $lastSeq = static::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->max('month_sequence') ?? 0;
            $model->month_sequence = $lastSeq + 1;

            // Format DDMMYYYY
            $datePrefix = now('Asia/Jakarta')->format('dmY');
            $model->reorder_code = sprintf('PU %s %03d', $datePrefix, $model->month_sequence);
        });
    }

    public function receivings()
    {
        return $this->hasMany(ProductReceived::class, 'reorder_id');
    }
}
