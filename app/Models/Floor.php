<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Floor extends Model
{
    protected $fillable = ['name', 'width', 'height', 'sort_order', 'outline', 'zones'];

    protected $casts = ['outline' => 'array', 'zones' => 'array'];

    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class)->orderBy('label');
    }
}
