<?php

namespace App\Events;

use App\Events\Concerns\BroadcastsSafely;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ReservationsChanged implements ShouldBroadcastNow
{
    use BroadcastsSafely, Dispatchable, InteractsWithSockets {
        BroadcastsSafely::dispatch insteadof Dispatchable;
    }

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
