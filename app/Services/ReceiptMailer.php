<?php

namespace App\Services;

use App\Mail\OrderReceipt;
use App\Mail\ReservationReceipt;
use App\Models\Order;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ReceiptMailer
{
    public static function reservation(Reservation $r): void
    {
        self::send($r->customer_email, fn () => new ReservationReceipt($r));
    }

    public static function order(Order $o): void
    {
        self::send($o->customer_email, fn () => new OrderReceipt($o));
    }

    private static function send(?string $to, callable $make): void
    {
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send($make());
        } catch (Throwable $e) {
            report($e);
        }
    }
}
