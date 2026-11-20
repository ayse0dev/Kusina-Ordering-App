<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Address → coordinates, used for the delivery-zone check (and required by GrabExpress).
 * Default: OpenStreetMap Nominatim (free; keep volume low, results are cached).
 * Set KUSINA_GEOCODER=none to disable.
 */
class Geocoder
{
    public function enabled(): bool
    {
        return config('kusina.delivery.geocoder.driver') === 'nominatim';
    }

    /** @return array{lat: float, lng: float}|null */
    public function locate(string $address): ?array
    {
        $address = trim(preg_replace('/\s+/', ' ', $address));
        if (! $this->enabled() || $address === '') {
            return null;
        }

        return Cache::remember('geocode.'.md5(mb_strtolower($address)), now()->addDays(14), function () use ($address) {
            try {
                $r = Http::withHeaders(['User-Agent' => config('kusina.delivery.geocoder.user_agent')])
                    ->acceptJson()->timeout(8)
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q' => $address, 'format' => 'json', 'limit' => 1, 'countrycodes' => config('kusina.delivery.geocoder.country'),
                    ]);
                $hit = $r->successful() ? $r->json(0) : null;

                return $hit ? ['lat' => (float) $hit['lat'], 'lng' => (float) $hit['lon']] : null;
            } catch (Throwable) {
                return null;   // never block ordering because the map service is down
            }
        });
    }

    /** Coordinates of the restaurant (configured, otherwise geocoded from its address). */
    public function pickup(): ?array
    {
        $p = config('kusina.delivery.pickup');
        if (! empty($p['lat']) && ! empty($p['lng'])) {
            return ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']];
        }

        return ($p['address'] ?? '') !== '' ? $this->locate($p['address']) : null;
    }

    /** Great-circle distance in kilometres. */
    public static function distanceKm(array $a, array $b): float
    {
        $r = 6371.0;
        $dLat = deg2rad($b['lat'] - $a['lat']);
        $dLng = deg2rad($b['lng'] - $a['lng']);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($h)));
    }
}
