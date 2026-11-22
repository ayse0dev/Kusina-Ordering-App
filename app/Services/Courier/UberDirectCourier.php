<?php

namespace App\Services\Courier;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Order;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Uber Direct (available in Canada). Written against Uber's published "Direct" API:
 * OAuth client-credentials, delivery quotes, deliveries, signed status webhooks.
 * NOT verified against a live account: test in Uber's sandbox before going live.
 */
class UberDirectCourier implements CourierProvider
{
    private const AUTH = 'https://auth.uber.com/oauth/v2/token';
    private const API = 'https://api.uber.com/v1/customers';

    public function key(): string
    {
        return 'uber';
    }

    private function cfg(string $k): ?string
    {
        return config("kusina.delivery.uber.$k");
    }

    public function configured(): bool
    {
        return $this->cfg('customer_id') && $this->cfg('client_id') && $this->cfg('client_secret') && $this->cfg('signing_key')
            && $this->pickupAddress() !== '';
    }

    private function pickupAddress(): string
    {
        return (string) (config('kusina.delivery.pickup.address') ?: config('kusina.contact.address'));
    }

    private function token(): string
    {
        return Cache::remember('courier.uber.token', 3000, function () {
            $r = Http::asForm()->timeout(15)->post(self::AUTH, [
                'client_id' => $this->cfg('client_id'),
                'client_secret' => $this->cfg('client_secret'),
                'grant_type' => 'client_credentials',
                'scope' => 'eats.deliveries',
            ]);
            if (! $r->successful() || ! $r->json('access_token')) {
                throw new CourierException('Uber authentication failed.');
            }

            return $r->json('access_token');
        });
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token())->acceptJson()->asJson()->timeout(20);
    }

    private function url(string $path): string
    {
        return self::API.'/'.$this->cfg('customer_id').$path;
    }

    public function quote(string $address, ?array $dropoff): CourierQuote
    {
        $body = ['pickup_address' => $this->pickupAddress(), 'dropoff_address' => $address];
        $p = config('kusina.delivery.pickup');
        if (! empty($p['lat']) && ! empty($p['lng'])) {
            $body += ['pickup_latitude' => (float) $p['lat'], 'pickup_longitude' => (float) $p['lng']];
        }
        if ($dropoff) {
            $body += ['dropoff_latitude' => $dropoff['lat'], 'dropoff_longitude' => $dropoff['lng']];
        }

        $r = $this->http()->post($this->url('/delivery_quotes'), $body);
        if (! $r->successful()) {
            throw new CourierException($r->json('message') ?: 'Uber could not quote this address.');
        }

        // Safety: never charge a quote in a different currency than the restaurant's.
        if (strtoupper((string) $r->json('currency')) !== strtoupper(config('kusina.currency'))) {
            throw new CourierException('Courier quoted in an unexpected currency.');
        }

        return new CourierQuote(
            fee: round(((int) $r->json('fee')) / 100, 2),
            etaMinutes: (int) ($r->json('duration') ?: 40),
            provider: 'uber',
            quoteId: $r->json('id'),
            expiresAt: $r->json('expires') ? CarbonImmutable::parse($r->json('expires')) : null,
        );
    }

    public function book(Order $order, Delivery $delivery): CourierBooking
    {
        $order->loadMissing('items');
        $p = config('kusina.delivery.pickup');

        $payload = array_filter([
            'quote_id' => ($delivery->quote_id && $delivery->created_at?->gt(now()->subMinutes(10))) ? $delivery->quote_id : null,   // quotes expire quickly
            'pickup_name' => $p['name'] ?: 'Kusi Nna',
            'pickup_address' => $this->pickupAddress(),
            'pickup_phone_number' => $p['phone'] ? Phone::e164($p['phone']) : null,
            'dropoff_name' => $order->customer_name,
            'dropoff_address' => $order->delivery_address,
            'dropoff_phone_number' => Phone::e164($order->customer_phone),
            'dropoff_notes' => $order->notes,
            'manifest_items' => $order->items->map(fn ($i) => ['name' => $i->name, 'quantity' => (int) $i->qty, 'size' => 'small'])->all(),
            'external_id' => $order->code,
            'undeliverable_action' => 'return',
        ], fn ($v) => $v !== null && $v !== '');

        $r = $this->http()->post($this->url('/deliveries'), $payload);
        if (! $r->successful() || ! $r->json('id')) {
            throw new CourierException($r->json('message') ?: 'Uber could not create the delivery.');
        }

        return new CourierBooking(
            providerId: $r->json('id'),
            status: $this->map($r->json('status')),
            trackingUrl: $r->json('tracking_url'),
            courierName: $r->json('courier.name'),
            courierPhone: $r->json('courier.phone_number'),
        );
    }

    public function cancel(Delivery $delivery): void
    {
        if (! $delivery->provider_delivery_id) {
            return;
        }
        $r = $this->http()->post($this->url('/deliveries/'.$delivery->provider_delivery_id.'/cancel'));
        if (! $r->successful()) {
            throw new CourierException($r->json('message') ?: 'Uber could not cancel the delivery.');
        }
    }

    /** HMAC-SHA256 of the raw body, hex, using the webhook signing key. */
    public function verifyWebhook(Request $request): bool
    {
        $key = $this->cfg('signing_key');
        $sig = $request->header('X-Uber-Signature') ?: $request->header('X-Postmates-Signature');
        if (! $key || ! $sig) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $request->getContent(), $key), strtolower(trim($sig)));
    }

    public function parseWebhook(Request $request): ?DeliveryUpdate
    {
        $kind = $request->json('kind');
        if ($kind !== null && $kind !== 'event.delivery_status') {
            return null;
        }

        $id = $request->json('delivery_id') ?: $request->json('data.id');
        $status = $request->json('status') ?: $request->json('data.status');
        if (! $id || ! $status) {
            return null;
        }

        return new DeliveryUpdate(
            providerId: (string) $id,
            status: $this->map($status),
            trackingUrl: $request->json('data.tracking_url'),
            courierName: $request->json('data.courier.name'),
            courierPhone: $request->json('data.courier.phone_number'),
            note: 'uber: '.$status,
        );
    }

    private function map(?string $s): DeliveryStatus
    {
        return match ($s) {
            'pickup' => DeliveryStatus::PickingUp,
            'pickup_complete', 'dropoff' => DeliveryStatus::InTransit,
            'delivered' => DeliveryStatus::Delivered,
            'canceled', 'cancelled' => DeliveryStatus::Cancelled,
            'returned', 'failed' => DeliveryStatus::Failed,
            default => DeliveryStatus::Requested,   // pending
        };
    }
}
