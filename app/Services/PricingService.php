<?php

namespace App\Services;

use App\Enums\OrderType;
use App\Models\Voucher;

class PricingService
{
    public function quote(float $subtotal, OrderType $type, array $codes = [], bool $lock = false, ?float $deliveryFee = null): array
    {
        $errors = [];
        $promo = null;
        $gift = null;

        $codes = collect($codes)->map(fn ($c) => Voucher::normalize((string) $c))->filter()->unique()->take(4);

        foreach ($codes as $code) {
            $query = Voucher::where('code', $code);
            $voucher = ($lock ? $query->lockForUpdate() : $query)->first();

            if (! $voucher || ! $voucher->isUsable()) {
                $errors[$code] = 'This code is not valid or has expired.';
                continue;
            }

            if ($voucher->kind === 'gift') {
                if ($gift) {
                    $errors[$code] = 'Only one gift voucher can be used per order.';
                } elseif ((float) $voucher->balance <= 0) {
                    $errors[$code] = 'This gift voucher has no balance left.';
                } else {
                    $gift = $voucher;
                }
                continue;
            }

            if ($promo) {
                $errors[$code] = 'Only one discount code can be used per order.';
            } elseif ($voucher->max_uses !== null && $voucher->uses >= $voucher->max_uses) {
                $errors[$code] = 'This code has reached its usage limit.';
            } elseif ($voucher->min_spend !== null && $subtotal < (float) $voucher->min_spend) {
                $errors[$code] = 'Spend at least $'.number_format($voucher->min_spend, 2).' to use this code.';
            } else {
                $promo = $voucher;
            }
        }

        $discount = 0.0;
        if ($promo) {
            $discount = $promo->type === 'percent'
                ? round($subtotal * min(100, (float) $promo->value) / 100, 2)
                : (float) $promo->value;
            $discount = min($discount, $subtotal);
        }

        $fee = $type === OrderType::Delivery ? ($deliveryFee ?? (float) config('kusina.delivery_fee')) : 0.0;
        $taxable = $subtotal - $discount + $fee;
        $tax = round($taxable * (float) config('kusina.tax_rate'), 2);
        $total = round($taxable + $tax, 2);
        $giftApplied = $gift ? min((float) $gift->balance, $total) : 0.0;

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'delivery_fee' => round($fee, 2),
            'tax' => $tax,
            'total' => $total,
            'gift_applied' => round($giftApplied, 2),
            'amount_due' => round($total - $giftApplied, 2),
            'errors' => $errors,
            'promo' => $promo,
            'gift' => $gift,
        ];
    }
}
