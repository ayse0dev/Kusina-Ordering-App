<?php

namespace App\Services\Courier;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Http\Request;

/** The restaurant's own driver: flat fee, staff assign the driver and move the order along. */
class ManualCourier implements CourierProvider
{
    public function key(): string
    {
        return 'manual';
    }

    public function configured(): bool
    {
        return true;
    }

    public function quote(string $address, ?array $dropoff): CourierQuote
    {
        return new CourierQuote((float) config('kusina.delivery_fee'), (int) config('kusina.delivery.manual_eta', 45), 'manual');
    }

    public function book(Order $order, Delivery $delivery): CourierBooking
    {
        // Nothing to call: staff assign a driver from the order board.
        return new CourierBooking('manual-'.$delivery->id, DeliveryStatus::Requested);
    }

    public function cancel(Delivery $delivery): void {}

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): ?DeliveryUpdate
    {
        return null;
    }
}
