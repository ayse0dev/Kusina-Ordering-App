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

        // Authenticity comes only from the provider's signature / shared secret.
        if (! $courier->verifyWebhook($request)) {
            abort(403, 'Invalid signature');
        }

        $update = $courier->parseWebhook($request);

        if ($update) {
            // Match on provider + id so one provider can never touch another's deliveries.
            $delivery = Delivery::where('provider', $provider)->where('provider_delivery_id', $update->providerId)->first();
            if ($delivery) {
                $deliveries->applyUpdate($delivery, $update);
            }
        }

        // Always 200 for verified events so the provider doesn't retry unknown/duplicate ones forever.
        return response()->json(['received' => true]);
    }
}
