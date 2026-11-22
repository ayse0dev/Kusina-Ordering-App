<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Services\Courier\CourierManager;
use App\Services\DeliveryService;
use Illuminate\Http\Request;

class CourierWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, CourierManager $couriers, DeliveryService $deliveries)
    {
        abort_unless(in_array($provider, $couriers->keys(), true) && $provider !== 'manual', 404);
        $courier = $couriers->provider($provider);

        if (! $courier->verifyWebhook($request)) {
            abort(403, 'Invalid signature');
        }

        $update = $courier->parseWebhook($request);

        if ($update) {
            $delivery = Delivery::where('provider', $provider)->where('provider_delivery_id', $update->providerId)->first();
            if ($delivery) {
                $deliveries->applyUpdate($delivery, $update);
            }
        }

        return response()->json(['received' => true]);
    }
}
