<?php

namespace App\Services\Courier;

use Carbon\CarbonImmutable;

final class CourierQuote
{
    public function __construct(
        public readonly float $fee,
        public readonly int $etaMinutes,
        public readonly string $provider,
        public readonly ?string $quoteId = null,
        public readonly ?CarbonImmutable $expiresAt = null,
    ) {}
}
