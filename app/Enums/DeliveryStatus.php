<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';          // waiting for the kitchen / for a courier to be requested
    case Requested = 'requested';      // courier requested, finding a driver
    case PickingUp = 'picking_up';     // driver heading to the restaurant
    case InTransit = 'in_transit';     // food collected, on the way
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Failed = 'failed';            // courier could not be booked or could not deliver

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

    /** Forward progress order; used to ignore out-of-order provider updates. */
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
