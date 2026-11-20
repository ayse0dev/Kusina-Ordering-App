<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Order received',
            self::Preparing => 'Preparing',
            self::Ready => 'Ready',
            self::OutForDelivery => 'Out for delivery',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** The single allowed forward transition for the given order type. */
    public function next(OrderType $type): ?self
    {
        return match ($this) {
            self::Pending => self::Preparing,
            self::Preparing => self::Ready,
            self::Ready => $type === OrderType::Delivery ? self::OutForDelivery : self::Completed,
            self::OutForDelivery => self::Completed,
            default => null,
        };
    }

    /** Steps shown on the customer's tracking timeline. */
    public static function timeline(OrderType $type): array
    {
        $steps = [self::Pending, self::Preparing, self::Ready];
        if ($type === OrderType::Delivery) {
            $steps[] = self::OutForDelivery;
        }
        $steps[] = self::Completed;

        return $steps;
    }
}
