<?php

namespace App\Events;

use App\Models\Order;
use App\Events\Concerns\BroadcastsSafely;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class OrderUpdated implements ShouldBroadcastNow
{
    use BroadcastsSafely, Dispatchable, InteractsWithSockets {
        BroadcastsSafely::dispatch insteadof Dispatchable;
    }

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('staff'), new Channel('orders.'.$this->order->code)];
    }

    public function broadcastAs(): string
    {
        return 'order.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'code' => $this->order->code,
            'status' => $this->order->status->value,
            'payment_status' => $this->order->payment_status,
        ];
    }
}
