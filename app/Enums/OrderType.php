<?php

namespace App\Enums;

enum OrderType: string
{
    case DineIn = 'dine_in';
    case Pickup = 'pickup';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine-in',
            self::Pickup => 'Pickup',
            self::Delivery => 'Delivery',
        };
    }
}
