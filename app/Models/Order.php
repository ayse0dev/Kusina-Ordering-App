<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'code', 'customer_name', 'customer_phone', 'customer_email', 'payment_detail', 'payment_account', 'payment_ref', 'order_type', 'table_number', 'delivery_address', 'notes',
        'status', 'payment_method', 'payment_status', 'paymongo_session_id', 'stripe_session_id',
        'discount', 'tax', 'gift_applied', 'amount_due',
        'subtotal', 'delivery_fee', 'total', 'paid_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'order_type' => OrderType::class,
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'gift_applied' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public static function generateCode(): string
    {
        do {
            $code = 'KUS-'.Str::upper(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    public function scopeVisibleToStaff(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereIn('payment_method', ['counter', 'cash'])->orWhere('payment_status', 'paid'));
    }

    public function isAwaitingPayment(): bool
    {
        return $this->payment_method === 'online' && $this->payment_status !== 'paid';
    }
}
