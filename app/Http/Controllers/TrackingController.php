<?php

namespace App\Http\Controllers;

use App\Models\Order;

class TrackingController extends Controller
{
    public function show(string $code)
    {
        $order = Order::with(['items.options', 'delivery'])->where('code', $code)->firstOrFail();

        return view('track.show', ['order' => $order, 'delivery' => $this->deliveryInfo($order)]);
    }

    /** Customer-safe delivery details (no internal ids or failure internals). */
    private function deliveryInfo(Order $order): ?array
    {
        $d = $order->delivery;
        if (! $d || $order->status->value === 'cancelled') {
            return null;
        }

        return [
            'status' => $d->status->value,
            'label' => $d->status === \App\Enums\DeliveryStatus::Failed ? 'We\'re arranging your delivery' : $d->status->label(),
            'courier' => $d->courier_name,
            'phone' => $d->status->isActive() ? $d->courier_phone : null,
            'tracking' => $d->status->isActive() ? $d->tracking_url : null,
            'eta' => $d->eta_minutes,
        ];
    }

    public function status(string $code)
    {
        $order = Order::where('code', $code)->firstOrFail();

        return response()->json([
            'status' => $order->status->value,
            'payment_status' => $order->payment_status,
            'delivery' => $this->deliveryInfo($order),
        ]);
    }
}
