<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemOption extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_item_id', 'group_name', 'name', 'price_delta'];
}
