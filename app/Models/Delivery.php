<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $fillable = [
        'order_id', 'provider', 'status', 'provider_delivery_id', 'quote_id', 'quoted_fee', 'fee_source', 'eta_minutes',
        'dropoff_lat', 'dropoff_lng', 'tracking_url', 'courier_name', 'courier_phone', 'failure_reason',
        'dispatched_at', 'delivered_at',
    ];

    protected $casts = [
        'status' => DeliveryStatus::class,
        'quoted_fee' => 'decimal:2',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class)->latest('id');
    }

    public function log(DeliveryStatus $status, ?string $note = null): void
    {
        $this->events()->create(['status' => $status->value, 'note' => $note ? mb_substr($note, 0, 300) : null]);
    }
}
