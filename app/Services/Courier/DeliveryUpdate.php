<?php

namespace App\Services\Courier;

use App\Enums\DeliveryStatus;

final class DeliveryUpdate
{
    public function __construct(
        public readonly string $providerId,
        public readonly DeliveryStatus $status,
        public readonly ?string $trackingUrl = null,
        public readonly ?string $courierName = null,
        public readonly ?string $courierPhone = null,
        public readonly ?string $note = null,
    ) {}
}
