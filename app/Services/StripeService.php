<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeService
{
    private const API = 'https://api.stripe.com/v1';

    public function createCheckout(float $amount, string $name, string $reference, string $successUrl, string $cancelUrl): array
    {
        $secret = config('kusina.stripe.secret');
        if (! $secret) {
            throw new RuntimeException('Online payment is not configured.');
        }

        $response = Http::withToken($secret)->asForm()->acceptJson()->timeout(20)->post(self::API.'/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $reference,
            'metadata[reference]' => $reference,
            'expires_at' => now()->addMinutes(30)->timestamp,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower(config('kusina.currency')),
            'line_items[0][price_data][unit_amount]' => (int) round($amount * 100),
            'line_items[0][price_data][product_data][name]' => mb_substr($name, 0, 120),
        ]);

        if (! $response->successful() || ! $response->json('url')) {
            throw new RuntimeException('Payment provider error.');
        }

        return [$response->json('id'), $response->json('url')];
    }

    public function verifySignature(string $rawBody, ?string $header, int $tolerance = 300): bool
    {
        $secret = config('kusina.stripe.webhook_secret');
        if (! $secret || ! $header) {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $pair) {
            [$k, $v] = array_pad(explode('=', trim($pair), 2), 2, '');
            if ($k === 't') {
                $timestamp = $v;
            } elseif ($k === 'v1' && $v !== '') {
                $signatures[] = $v;
            }
        }

        if (! $timestamp || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }
}
