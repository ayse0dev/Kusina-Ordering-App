<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Option extends Model
{
    protected $fillable = ['option_group_id', 'name', 'price_delta', 'is_available', 'sort_order'];

    protected $casts = ['is_available' => 'boolean', 'price_delta' => 'decimal:2'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }
}
