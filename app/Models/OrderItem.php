<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'menu_item_id', 'name', 'unit_price', 'qty', 'line_total', 'notes'];

    public function options(): HasMany
    {
        return $this->hasMany(OrderItemOption::class);
    }
}
