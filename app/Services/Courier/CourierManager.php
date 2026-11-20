<?php

namespace App\Services\Courier;

use Illuminate\Support\Facades\Log;

class CourierManager
{
    /** @return array<string, class-string<CourierProvider>> */
    private const PROVIDERS = [
        'manual' => ManualCourier::class,
        'uber' => UberDirectCourier::class,
        'grab' => GrabExpressCourier::class,
    ];

    public function configuredKey(): string
    {
        $key = (string) config('kusina.delivery.provider', 'manual');

        return isset(self::PROVIDERS[$key]) ? $key : 'manual';
    }

    public function provider(string $key): CourierProvider
    {
        abort_unless(isset(self::PROVIDERS[$key]), 404);

        return app(self::PROVIDERS[$key]);
    }

    /**
     * The courier to use for NEW deliveries. If the chosen one is missing credentials we fall back
     * to the restaurant's own driver rather than failing every delivery order.
     */
    public function active(): CourierProvider
    {
        $p = $this->provider($this->configuredKey());

        if (! $p->configured()) {
            Log::warning("Courier '{$p->key()}' is selected but not fully configured; using manual delivery.");

            return $this->provider('manual');
        }

        return $p;
    }

    /** Provider for an existing delivery (the one it was booked with, regardless of today's setting). */
    public function for(string $key): CourierProvider
    {
        return $this->provider($key);
    }

    public function keys(): array
    {
        return array_keys(self::PROVIDERS);
    }
}
