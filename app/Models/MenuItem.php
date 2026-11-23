<?php

namespace App\Models;

use App\Support\PhotoUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class MenuItem extends Model
{
    protected $fillable = ['category_id', 'name', 'description', 'base_price', 'image_path', 'is_available', 'sort_order'];

    protected $casts = ['is_available' => 'boolean', 'base_price' => 'decimal:2'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(OptionGroup::class)->orderBy('sort_order');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return PhotoUrl::resolve($this->image_path);
    }
}
