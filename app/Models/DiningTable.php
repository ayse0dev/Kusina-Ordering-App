<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningTable extends Model
{
    protected $fillable = ['floor_id', 'label', 'seats', 'shape', 'x', 'y', 'w', 'h', 'is_active', 'is_vip'];

    protected $casts = ['is_active' => 'boolean', 'is_vip' => 'boolean'];

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
