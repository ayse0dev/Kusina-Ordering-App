<?php

namespace App\Events\Concerns;

use Throwable;

trait BroadcastsSafely
{
    public static function dispatch(...$arguments)
    {
        try {
            return event(new static(...$arguments));
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
