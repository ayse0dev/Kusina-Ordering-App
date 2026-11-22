<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Voucher extends Model
{
    protected $fillable = [
        'code', 'kind', 'status', 'type', 'value', 'balance', 'min_spend', 'max_uses', 'uses', 'expires_at', 'description',
        'buyer_name', 'buyer_email', 'recipient_name', 'recipient_email', 'message', 'public_token', 'stripe_session_id', 'paid_at',
    ];

    protected $casts = [
        'value' => 'decimal:2', 'balance' => 'decimal:2', 'min_spend' => 'decimal:2',
        'expires_at' => 'datetime', 'paid_at' => 'datetime',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class);
    }

    public static function normalize(string $code): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9\-]/', '', trim($code)));
    }

    public static function generateGiftCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $chunk = fn () => collect(range(1, 4))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->join('');
            $code = 'GIFT-'.$chunk().'-'.$chunk();
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function isUsable(): bool
    {
        return $this->status === 'active' && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function label(): string
    {
        if ($this->kind === 'gift') {
            return 'Gift voucher';
        }

        return $this->type === 'percent' ? rtrim(rtrim((string) $this->value, '0'), '.').'% off' : '$'.number_format($this->value, 2).' off';
    }
}
