<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Reservation extends Model
{
    /** Statuses that occupy a table. */
    public const BLOCKING = ['confirmed', 'seated'];

    protected $fillable = [
        'code', 'dining_table_id', 'customer_name', 'customer_phone', 'party_size',
        'starts_at', 'ends_at', 'status', 'notes',
    ];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public static function generateCode(): string
    {
        do {
            $code = 'RSV-'.Str::upper(Str::random(7));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function scopeBlocking(Builder $q): Builder
    {
        return $q->whereIn('status', self::BLOCKING);
    }

    /** Reservations overlapping [start, end). */
    public function scopeOverlapping(Builder $q, $start, $end): Builder
    {
        return $q->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }
}
