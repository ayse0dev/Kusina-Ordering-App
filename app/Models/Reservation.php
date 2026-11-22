<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Reservation extends Model
{
    public const BLOCKING = ['confirmed', 'seated'];

    protected $fillable = [
        'code', 'dining_table_id', 'customer_name', 'customer_phone', 'customer_email', 'party_size',
        'starts_at', 'ends_at', 'status', 'notes',
        'payment_method', 'payment_detail', 'payment_account', 'payment_status', 'payment_ref', 'subtotal', 'tax', 'total', 'paid_at',
    ];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'paid_at' => 'datetime', 'subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2'];

    public static function generateCode(): string
    {
        do {
            $code = 'RSV-'.Str::upper(Str::random(7));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function paymentLabel(): string
    {
        $label = config("kusina.payment_methods.{$this->payment_method}.label", ucfirst((string) $this->payment_method));

        return $this->payment_detail ? "$label ({$this->payment_detail})" : $label;
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function scopeBlocking(Builder $q): Builder
    {
        return $q->whereIn('status', self::BLOCKING);
    }

    public function scopeOverlapping(Builder $q, $start, $end): Builder
    {
        return $q->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }
}
