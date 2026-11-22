<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Requested = 'requested';
    case PickingUp = 'picking_up';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for courier',
            self::Requested => 'Finding a driver',
            self::PickingUp => 'Driver on the way to the restaurant',
            self::InTransit => 'On the way to you',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Delivery cancelled',
            self::Failed => 'Delivery problem',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Pending => 0, self::Requested => 1, self::PickingUp => 2, self::InTransit => 3, self::Delivered => 4,
            default => -1,
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Requested, self::PickingUp, self::InTransit], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled], true);
    }
}
