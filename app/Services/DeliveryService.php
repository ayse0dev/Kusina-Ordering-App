<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\Courier\CourierBooking;
use App\Services\Courier\CourierException;
use App\Services\Courier\CourierManager;
use App\Services\Courier\DeliveryUpdate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeliveryService
{
    private const SESSION_KEY = 'delivery_quote';
    private const QUOTE_TTL_MINUTES = 10;

    public function __construct(private CourierManager $couriers, private Geocoder $geocoder) {}

    public function quote(string $address): array
    {
        $address = $this->clean($address);
        $key = md5(mb_strtolower($address));

        $cached = session(self::SESSION_KEY);
        if ($cached && $cached['key'] === $key && $cached['expires'] > now()->timestamp) {
            return $cached['quote'];
        }

        $coords = $this->geocoder->locate($address);
        $this->assertWithinZone($coords);

        $courier = $this->couriers->active();
        $message = null;

        try {
            $q = $courier->quote($address, $coords);
            $result = [
                'fee' => $q->fee, 'eta' => $q->etaMinutes, 'provider' => $courier->key(), 'source' => $courier->key() === 'manual' ? 'flat' : 'courier',
                'quote_id' => $q->quoteId, 'lat' => $coords['lat'] ?? null, 'lng' => $coords['lng'] ?? null, 'message' => null,
            ];
        } catch (CourierException $e) {
            report($e);
            $message = 'We could not get a live courier price, so the standard delivery fee applies.';
            $manual = $this->couriers->provider('manual')->quote($address, $coords);
            $result = [
                'fee' => $manual->fee, 'eta' => $manual->etaMinutes, 'provider' => $courier->key(), 'source' => 'flat',
                'quote_id' => null, 'lat' => $coords['lat'] ?? null, 'lng' => $coords['lng'] ?? null, 'message' => $message,
            ];
        }

        session([self::SESSION_KEY => ['key' => $key, 'expires' => now()->addMinutes(self::QUOTE_TTL_MINUTES)->timestamp, 'quote' => $result]]);

        return $result;
    }

    private function assertWithinZone(?array $coords): void
    {
        $max = config('kusina.delivery.max_km');
        $pickup = $this->geocoder->pickup();

        if (! $max || ! $coords || ! $pickup) {
            return;
        }

        if (Geocoder::distanceKm($pickup, $coords) > (float) $max) {
            throw ValidationException::withMessages([
                'delivery_address' => "Sorry, that address is outside our delivery area (about {$max} km from the restaurant).",
            ]);
        }
    }

    private function safeUrl(?string $url): ?string
    {
        return $url && preg_match('#^https://[^\s<>"\']+$#i', $url) ? mb_substr($url, 0, 500) : null;
    }

    private function clean(string $address): string
    {
        return trim(preg_replace('/\s+/', ' ', $address));
    }

    public function createFor(Order $order, array $quote): Delivery
    {
        return Delivery::create([
            'order_id' => $order->id,
            'provider' => $quote['provider'] ?? $this->couriers->active()->key(),
            'status' => DeliveryStatus::Pending,
            'quote_id' => $quote['quote_id'] ?? null,
            'quoted_fee' => $order->delivery_fee,
            'fee_source' => $quote['source'] ?? 'flat',
            'eta_minutes' => $quote['eta'] ?? null,
            'dropoff_lat' => $quote['lat'] ?? null,
            'dropoff_lng' => $quote['lng'] ?? null,
        ]);
    }

    public function dispatch(Order $order): ?Delivery
    {
        return DB::transaction(function () use ($order) {
            $delivery = Delivery::where('order_id', $order->id)->lockForUpdate()->first();

            if (! $delivery || $delivery->status->isActive() || $delivery->status->isFinal()) {
                return $delivery;
            }
            if ($order->isAwaitingPayment() || $order->status->isFinal()) {
                return $delivery;
            }

            $courier = $this->couriers->for($delivery->provider);

            if (! $courier->configured()) {
                $courier = $this->couriers->provider('manual');
                $delivery->provider = 'manual';
            }

            try {
                $booking = $courier->book($order, $delivery);
                $this->applyBooking($delivery, $booking);
            } catch (CourierException|Throwable $e) {
                report($e);
                $delivery->update(['status' => DeliveryStatus::Failed, 'failure_reason' => mb_substr($e->getMessage(), 0, 300)]);
                $delivery->log(DeliveryStatus::Failed, 'Dispatch failed: '.$e->getMessage());
            }

            return $delivery->refresh();
        });
    }

    private function applyBooking(Delivery $delivery, CourierBooking $b): void
    {
        $delivery->update([
            'status' => $b->status,
            'provider_delivery_id' => $b->providerId,
            'tracking_url' => $this->safeUrl($b->trackingUrl),
            'courier_name' => $b->courierName,
            'courier_phone' => $b->courierPhone,
            'failure_reason' => null,
            'dispatched_at' => now(),
        ]);
        $delivery->log($b->status, 'Courier requested ('.$delivery->provider.')');
    }

    public function dispatchIfDue(Order $order, OrderStatus $now): void
    {
        if ($order->order_type->value !== 'delivery' || $now->value !== config('kusina.delivery.dispatch_on', 'preparing')) {
            return;
        }
        $this->dispatch($order);
    }

    public function cancelFor(Order $order): void
    {
        $delivery = Delivery::where('order_id', $order->id)->first();
        if (! $delivery || $delivery->status->isFinal()) {
            return;
        }

        try {
            if ($delivery->status->isActive()) {
                $this->couriers->for($delivery->provider)->cancel($delivery);
            }
        } catch (Throwable $e) {
            report($e);
            $delivery->log($delivery->status, 'Courier cancel failed: '.$e->getMessage());
        }

        $delivery->update(['status' => DeliveryStatus::Cancelled]);
        $delivery->log(DeliveryStatus::Cancelled, 'Order cancelled');
    }

    public function syncManual(Order $order, OrderStatus $to): void
    {
        $d = Delivery::where('order_id', $order->id)->where('provider', 'manual')->first();
        $target = match ($to) {
            OrderStatus::OutForDelivery => DeliveryStatus::InTransit,
            OrderStatus::Completed => DeliveryStatus::Delivered,
            default => null,
        };

        if ($d && $target && ! $d->status->isFinal()) {
            $d->update(['status' => $target, 'delivered_at' => $target === DeliveryStatus::Delivered ? now() : $d->delivered_at]);
            $d->log($target, 'Updated by staff');
        }
    }

    public function applyUpdate(Delivery $delivery, DeliveryUpdate $u): void
    {
        $new = $u->status;
        $cur = $delivery->status;

        if ($cur->isFinal() || ($new->rank() >= 0 && $cur->rank() > $new->rank() && $cur !== DeliveryStatus::Failed)) {
            return;
        }

        $changed = $new !== $cur;

        $delivery->update(array_filter([
            'status' => $new,
            'tracking_url' => $this->safeUrl($u->trackingUrl) ?: $delivery->tracking_url,
            'courier_name' => $u->courierName ?: $delivery->courier_name,
            'courier_phone' => $u->courierPhone ?: $delivery->courier_phone,
            'delivered_at' => $new === DeliveryStatus::Delivered ? now() : null,
            'failure_reason' => $new === DeliveryStatus::Failed ? ($u->note ?: 'Courier reported a problem') : null,
        ], fn ($v) => $v !== null));

        if ($changed) {
            $delivery->log($new, $u->note);
        }

        app(OrderService::class)->courierUpdate($delivery->order, $new);
    }

    public function assignDriver(Delivery $delivery, string $name, ?string $phone): void
    {
        $delivery->update([
            'courier_name' => $name, 'courier_phone' => $phone,
            'status' => $delivery->status->rank() < DeliveryStatus::PickingUp->rank() ? DeliveryStatus::PickingUp : $delivery->status,
            'failure_reason' => null,
        ]);
        $delivery->log($delivery->status, "Driver assigned: {$name}");
    }
}
