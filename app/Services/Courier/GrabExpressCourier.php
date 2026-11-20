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
 * GrabExpress (Southeast Asia only: not available in Canada). Written against Grab's published
 * GrabExpress partner API: OAuth client-credentials, delivery quotes, deliveries, status webhooks.
 * NOT verified against a live partner account: test in Grab's sandbox before going live.
 *
 * Grab needs coordinates for both ends, so the delivery address must geocode.
 */
class GrabExpressCourier implements CourierProvider
{
    private const AUTH = 'https://partner-api.grab.com/grabid/v1/oauth2/token';

    public function key(): string
    {
        return 'grab';
    }

    private function cfg(string $k): ?string
    {
        return config("kusina.delivery.grab.$k");
    }

    public function configured(): bool
    {
        $p = config('kusina.delivery.pickup');

        return $this->cfg('client_id') && $this->cfg('client_secret') && $this->cfg('webhook_token')
            && ! empty($p['lat']) && ! empty($p['lng']);
    }

    private function base(): string
    {
        return $this->cfg('env') === 'production'
            ? 'https://partner-api.grab.com/grab-express'
            : 'https://partner-api.grab.com/grab-express-sandbox';
    }

    private function token(): string
    {
        return Cache::remember('courier.grab.token', 3000, function () {
            $r = Http::asJson()->timeout(15)->post(self::AUTH, [
                'client_id' => $this->cfg('client_id'),
                'client_secret' => $this->cfg('client_secret'),
                'grant_type' => 'client_credentials',
                'scope' => 'grab_express.partner_deliveries',
            ]);
            if (! $r->successful() || ! $r->json('access_token')) {
                throw new CourierException('Grab authentication failed.');
            }

            return $r->json('access_token');
        });
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token())->acceptJson()->asJson()->timeout(20);
    }

    private function pickup(): array
    {
        $p = config('kusina.delivery.pickup');

        return [
            'address' => (string) ($p['address'] ?: config('kusina.contact.address')),
            'coordinates' => ['latitude' => (float) $p['lat'], 'longitude' => (float) $p['lng']],
        ];
    }

    private function packages(?Order $order = null, float $price = 0): array
    {
        $items = $order ? $order->items : collect();
        $qty = max(1, (int) $items->sum('qty'));

        return [[
            'name' => 'Kusina food order',
            'description' => $order ? 'Order '.$order->code : 'Hot food',
            'quantity' => $qty,
            'price' => $order ? (float) $order->subtotal : $price,
            'dimensions' => ['height' => 0, 'width' => 0, 'depth' => 0, 'weight' => 0],
        ]];
    }

    public function quote(string $address, ?array $dropoff): CourierQuote
    {
        if (! $dropoff) {
            throw new CourierException('That address could not be located on the map.');
        }

        $r = $this->http()->post($this->base().'/v1/deliveries/quotes', [
            'serviceType' => 'INSTANT',
            'vehicleType' => 'BIKE',
            'packages' => $this->packages(null, 0),
            'origin' => $this->pickup(),
            'destination' => ['address' => $address, 'coordinates' => ['latitude' => $dropoff['lat'], 'longitude' => $dropoff['lng']]],
        ]);
        if (! $r->successful() || ! $r->json('quotes.0')) {
            throw new CourierException($r->json('message') ?: 'Grab could not quote this address.');
        }

        $q = $r->json('quotes.0');
        $code = strtoupper((string) ($q['currency']['code'] ?? ''));
        if ($code !== strtoupper(config('kusina.currency'))) {
            // GrabExpress prices in local Southeast Asian currencies: refuse rather than mischarge.
            throw new CourierException("Grab quoted in {$code}, not ".config('kusina.currency').'.');
        }

        $exp = (int) ($q['currency']['exponent'] ?? 2);
        $eta = 40;
        if (! empty($q['estimatedTimeline']['dropoff'])) {
            $eta = max(10, (int) CarbonImmutable::now()->diffInMinutes(CarbonImmutable::parse($q['estimatedTimeline']['dropoff']), false));
        }

        return new CourierQuote(round(((float) $q['amount']) / (10 ** $exp), 2), $eta, 'grab');
    }

    public function book(Order $order, Delivery $delivery): CourierBooking
    {
        $order->loadMissing('items');
        if (! $delivery->dropoff_lat || ! $delivery->dropoff_lng) {
            throw new CourierException('The delivery address has no map coordinates.');
        }
        $p = config('kusina.delivery.pickup');

        $r = $this->http()->post($this->base().'/v1/deliveries', [
            'merchantOrderID' => $order->code,
            'serviceType' => 'INSTANT',
            'vehicleType' => 'BIKE',
            'codType' => 'REGULAR',
            'paymentMethod' => 'CASHLESS',
            'packages' => $this->packages($order),
            'origin' => $this->pickup(),
            'destination' => [
                'address' => $order->delivery_address,
                'coordinates' => ['latitude' => (float) $delivery->dropoff_lat, 'longitude' => (float) $delivery->dropoff_lng],
            ],
            'recipient' => ['firstName' => $order->customer_name, 'phone' => Phone::e164($order->customer_phone), 'smsEnabled' => true],
            'sender' => ['firstName' => $p['name'] ?: 'Kusina', 'companyName' => $p['name'] ?: 'Kusina',
                'phone' => $p['phone'] ? Phone::e164($p['phone']) : null, 'smsEnabled' => false],
        ]);
        if (! $r->successful() || ! $r->json('deliveryID')) {
            throw new CourierException($r->json('message') ?: 'Grab could not create the delivery.');
        }

        return new CourierBooking(
            providerId: $r->json('deliveryID'),
            status: $this->map($r->json('status')),
            trackingUrl: $r->json('trackURL'),
            courierName: $r->json('driver.name'),
            courierPhone: $r->json('driver.phone'),
        );
    }

    public function cancel(Delivery $delivery): void
    {
        if (! $delivery->provider_delivery_id) {
            return;
        }
        $r = $this->http()->delete($this->base().'/v1/deliveries/'.$delivery->provider_delivery_id);
        if (! $r->successful()) {
            throw new CourierException($r->json('message') ?: 'Grab could not cancel the delivery.');
        }
    }

    /** Grab webhooks are authenticated with a secret token we register in the callback URL/header. */
    public function verifyWebhook(Request $request): bool
    {
        $expected = $this->cfg('webhook_token');
        $given = $request->query('token') ?: $request->header('X-Webhook-Token');

        return $expected && $given && hash_equals($expected, (string) $given);
    }

    public function parseWebhook(Request $request): ?DeliveryUpdate
    {
        $id = $request->json('deliveryID');
        $status = $request->json('status');
        if (! $id || ! $status) {
            return null;
        }

        return new DeliveryUpdate(
            providerId: (string) $id,
            status: $this->map($status),
            trackingUrl: $request->json('trackURL'),
            courierName: $request->json('driver.name'),
            courierPhone: $request->json('driver.phone'),
            note: 'grab: '.$status,
        );
    }

    private function map(?string $s): DeliveryStatus
    {
        return match (strtoupper((string) $s)) {
            'PICKING_UP' => DeliveryStatus::PickingUp,
            'IN_DELIVERY' => DeliveryStatus::InTransit,
            'COMPLETED' => DeliveryStatus::Delivered,
            'CANCELED', 'CANCELLED' => DeliveryStatus::Cancelled,
            'FAILED', 'IN_RETURN', 'RETURNED' => DeliveryStatus::Failed,
            default => DeliveryStatus::Requested,   // QUEUING, ALLOCATING
        };
    }
}
