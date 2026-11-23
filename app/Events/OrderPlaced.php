<?php

namespace App\Events;

use App\Events\Concerns\BroadcastsSafely;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class OrderPlaced implements ShouldBroadcastNow
{
    use BroadcastsSafely, Dispatchable, InteractsWithSockets {
        BroadcastsSafely::dispatch insteadof Dispatchable;
    }

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('staff')];
    }

    public function broadcastAs(): string
    {
        return 'order.placed';
    }

    public function broadcastWith(): array
    {
        return ['code' => $this->order->code];
    }
}
