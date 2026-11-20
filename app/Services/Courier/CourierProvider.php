<?php

namespace App\Services\Courier;

use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * A delivery courier. Implementations translate between Kusina's neutral delivery model and one
 * provider's API, so the rest of the app never knows which courier is in use.
 */
interface CourierProvider
{
    /** Stable key stored on deliveries: manual | uber | grab. */
    public function key(): string;

    /** Are all credentials/settings present? (Manual is always configured.) */
    public function configured(): bool;

    /**
     * Price/ETA for delivering to an address.
     *
     * @param  array{lat: float, lng: float}|null  $dropoff  geocoded coordinates, when known
     *
     * @throws CourierException when the courier cannot serve this address
     */
    public function quote(string $address, ?array $dropoff): CourierQuote;

    /** @throws CourierException */
    public function book(Order $order, Delivery $delivery): CourierBooking;

    /** @throws CourierException */
    public function cancel(Delivery $delivery): void;

    /** Is this webhook request authentically from the provider? */
    public function verifyWebhook(Request $request): bool;

    /** Normalise a webhook payload into a neutral update (null = ignore the event). */
    public function parseWebhook(Request $request): ?DeliveryUpdate;
}
