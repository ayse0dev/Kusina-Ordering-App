<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\PhotoUrl;

class GalleryPhoto extends Model
{
    protected $fillable = ['path', 'caption', 'sort_order'];

    public function getUrlAttribute(): string
    {
        return PhotoUrl::resolve($this->path);
    }
}
