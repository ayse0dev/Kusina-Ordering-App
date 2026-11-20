<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherRedemption extends Model
{
    protected $fillable = ['voucher_id', 'order_id', 'amount'];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
