<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Tells open floor maps to re-fetch availability. Carries no data. */
class ReservationsChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function broadcastOn(): array
    {
        return [new Channel('reservations')];
    }

    public function broadcastAs(): string
    {
        return 'reservations.changed';
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
