<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\Courier\CourierManager;
use App\Services\DeliveryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeliveryAdminController extends Controller
{
    public function index(CourierManager $couriers)
    {
        $deliveries = Delivery::with('order')->latest()->paginate(25);

        $providers = collect($couriers->keys())->map(fn ($k) => [
            'key' => $k, 'configured' => $couriers->provider($k)->configured(), 'selected' => $couriers->configuredKey() === $k,
        ]);

        return view('admin.deliveries.index', [
            'deliveries' => $deliveries,
            'providers' => $providers,
            'activeKey' => $couriers->active()->key(),
            'configuredKey' => $couriers->configuredKey(),
            'pickupSet' => (config('kusina.delivery.pickup.address') ?: '') !== '',
        ]);
    }

    public function dispatchCourier(Order $order, DeliveryService $deliveries)
    {
        abort_unless($order->order_type->value === 'delivery', 422);

        $delivery = $deliveries->dispatch($order);
        if ($delivery?->failure_reason) {
            throw ValidationException::withMessages(['courier' => 'Courier request failed: '.$delivery->failure_reason]);
        }

        return response()->json(['ok' => true]);
    }

    public function cancel(Order $order, DeliveryService $deliveries)
    {
        $deliveries->cancelFor($order);

        return response()->json(['ok' => true]);
    }

    public function assign(Request $request, Order $order, DeliveryService $deliveries)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
        ]);

        $delivery = Delivery::where('order_id', $order->id)->firstOrFail();
        $deliveries->assignDriver($delivery, $data['name'], $data['phone'] ?? null);

        return response()->json(['ok' => true]);
    }
}
