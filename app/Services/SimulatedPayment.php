<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SimulatedPayment
{
    public static function rules(?string $method): array
    {
        $cfg = config('kusina.payment_methods');
        $wallet = $method === 'ewallet';
        $card = $method === 'card';

        return [
            'payment_method' => ['required', Rule::in(array_keys($cfg))],
            'payment_detail' => $wallet || $card
                ? ['required', 'string', Rule::in($wallet ? $cfg['ewallet']['options'] : $cfg['card']['banks'])]
                : ['nullable', 'string', 'max:40'],
            'account_number' => [$wallet ? 'required' : 'nullable', 'regex:/^[0-9+\s\-]{10,15}$/'],
            'card_holder' => [$card ? 'required' : 'nullable', 'string', 'max:60'],
            'card_number' => [$card ? 'required' : 'nullable', function ($attr, $value, $fail) {
                $d = preg_replace('/\D/', '', (string) $value);
                if (strlen($d) < 13 || strlen($d) > 19) {
                    $fail('Enter a valid card number.');
                }
            }],
            'card_expiry' => [$card ? 'required' : 'nullable', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/', function ($attr, $value, $fail) {
                if (preg_match('/^(\d{2})\/(\d{2})$/', (string) $value, $m) && now()->startOfMonth()->gt(\Carbon\Carbon::createFromDate(2000 + (int) $m[2], (int) $m[1], 1))) {
                    $fail('That card has expired.');
                }
            }],
        ];
    }

    public static function settle(string $method, array $data): array
    {
        $cfg = config("kusina.payment_methods.$method");
        $paid = (bool) ($cfg['paid_now'] ?? false);
        $digits = preg_replace('/\D/', '', (string) ($method === 'card' ? ($data['card_number'] ?? '') : ($data['account_number'] ?? '')));

        return [
            'payment_method' => $method,
            'payment_detail' => in_array($method, ['ewallet', 'card'], true) ? ($data['payment_detail'] ?? null) : null,
            'payment_account' => $digits !== '' ? '•••• '.substr($digits, -4) : null,
            'payment_status' => $paid ? 'paid' : 'unpaid',
            'payment_ref' => $paid ? 'SIM-'.Str::upper(Str::random(10)) : null,
            'paid_at' => $paid ? now() : null,
        ];
    }

    public static function reservationPrice(int $party): array
    {
        $subtotal = round(config('kusina.reservations.fee_per_guest') * $party, 2);
        $tax = round($subtotal * config('kusina.tax_rate'), 2);

        return ['subtotal' => $subtotal, 'tax' => $tax, 'total' => round($subtotal + $tax, 2)];
    }
}
