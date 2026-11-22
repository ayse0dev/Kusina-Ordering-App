<?php

namespace App\Services\Courier;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Http\Request;

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
