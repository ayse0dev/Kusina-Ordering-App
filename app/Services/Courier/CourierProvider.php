<?php

namespace App\Services\Courier;

use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Http\Request;

interface CourierProvider
{
    public function key(): string;

    public function configured(): bool;

    public function quote(string $address, ?array $dropoff): CourierQuote;

    public function book(Order $order, Delivery $delivery): CourierBooking;

    public function cancel(Delivery $delivery): void;

    public function verifyWebhook(Request $request): bool;

    public function parseWebhook(Request $request): ?DeliveryUpdate;
}
