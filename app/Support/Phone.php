<?php

namespace App\Support;

class Phone
{
    /** Best-effort E.164 (couriers require it). Assumes the configured country for 10-digit local numbers. */
    public static function e164(string $raw, ?string $countryCode = null): string
    {
        $countryCode ??= config('kusina.delivery.phone_country', '+1');
        $digits = preg_replace('/\D+/', '', $raw);

        if (str_starts_with(trim($raw), '+')) {
            return '+'.$digits;
        }

        $cc = ltrim($countryCode, '+');
        if (strlen($digits) === 10) {
            return '+'.$cc.$digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, $cc)) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }
}
