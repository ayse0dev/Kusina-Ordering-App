<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Services\OrderService;

class KitchenFeedController extends Controller
{
    public function __invoke(OrderService $orders)
    {
        $orders->sweepServed();

        $ready = Order::with('reservation:id,dining_table_id')
            ->where('order_type', OrderType::DineIn->value)
            ->where('status', OrderStatus::Ready->value)
            ->whereNotNull('reservation_id')
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->get();

        return response()->json([
            'ready' => $ready->map(fn (Order $o) => [
                'key' => 'o'.$o->id,
                'table_id' => $o->reservation?->dining_table_id,
                'age' => (int) $o->updated_at->diffInSeconds(now(), false),
            ])->filter(fn ($r) => $r['table_id'])->values(),
        ]);
    }
}
