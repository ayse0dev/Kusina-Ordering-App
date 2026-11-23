<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Floor;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\ReceiptPdf;

class TrackingController extends Controller
{
    public function show(string $code, OrderService $orders)
    {
        $orders->sweepServed();
        $code = strtoupper(trim($code));
        if (str_starts_with($code, 'RSV-')) {
            return redirect()->route('reservations.show', $code);
        }

        $order = Order::with(['items.options', 'delivery', 'reservation.table'])->where('code', $code)->first();
        if (! $order) {
            return response()->view('track.missing', [], 404);
        }

        return view('track.show', [
            'order' => $order,
            'delivery' => $this->deliveryInfo($order),
            'progress' => $this->progress($order),
            'milestones' => $this->milestones($order),
            'scene' => $this->scene($order),
        ]);
    }

    public function receipt(string $code)
    {
        $order = Order::with('items.options')->where('code', $code)->firstOrFail();

        return view('track.receipt', compact('order'));
    }

    public function receiptPdf(string $code)
    {
        $order = Order::with('items.options')->where('code', $code)->firstOrFail();

        return response(ReceiptPdf::order($order), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Receipt-'.$order->code.'.pdf"',
        ]);
    }

    public function status(string $code, OrderService $orders)
    {
        $orders->sweepServed();
        $order = Order::with('items')->where('code', strtoupper(trim($code)))->firstOrFail();

        return response()->json($this->statusPayload($order));
    }

    public function served(string $code, OrderService $orders)
    {
        $order = Order::with('items')->where('code', strtoupper(trim($code)))->firstOrFail();

        abort_unless($order->order_type === OrderType::DineIn && $order->status === OrderStatus::Ready, 422, 'This order is not waiting to be served.');
        abort_if($order->updated_at->diffInSeconds(now(), false) < 8, 422, 'Please wait for the waiter.');

        $orders->completeServed($order);

        return response()->json($this->statusPayload($order->fresh('items')));
    }

    private function statusPayload(Order $order): array
    {
        return [
            'status' => $order->status->value,
            'payment_status' => $order->payment_status,
            'delivery' => $this->deliveryInfo($order),
            'progress' => $this->progress($order),
            'milestones' => $this->milestones($order),
        ];
    }

    private function deliveryInfo(Order $order): ?array
    {
        $d = $order->delivery;
        if (! $d || $order->status->value === 'cancelled') {
            return null;
        }

        return [
            'status' => $d->status->value,
            'label' => $d->status === \App\Enums\DeliveryStatus::Failed ? 'We\'re arranging your delivery' : $d->status->label(),
            'courier' => $d->courier_name,
            'phone' => $d->status->isActive() ? $d->courier_phone : null,
            'tracking' => $d->status->isActive() ? $d->tracking_url : null,
            'eta' => $d->eta_minutes,
        ];
    }

    private function progress(Order $order): array
    {
        $total = $order->prepSeconds();
        $started = $order->status === OrderStatus::Preparing ? $order->preparingStartedAt() : null;

        $elapsed = match (true) {
            $order->status === OrderStatus::Pending, $order->status === OrderStatus::Cancelled => 0,
            $order->status === OrderStatus::Preparing => $started ? max(0, (int) $started->diffInSeconds(now(), false)) : 0,
            default => $total,
        };

        return [
            'total' => $total,
            'elapsed' => min($total, $elapsed),
            'remaining' => max(0, $total - $elapsed),
            'ready_at' => $started?->copy()->addSeconds($total)->format('g:i A'),
            'ready_age' => $order->status === OrderStatus::Ready ? (int) $order->updated_at->diffInSeconds(now(), false) : null,
        ];
    }

    private function milestones(Order $order): array
    {
        $times = $order->statusLogs()->orderBy('id')->get()->groupBy('status')->map(fn ($logs) => $logs->first()->created_at);
        $steps = OrderStatus::timeline($order->order_type);
        $current = array_search($order->status, $steps, true);
        $dineIn = $order->order_type === OrderType::DineIn;

        return collect($steps)->map(function (OrderStatus $step, int $i) use ($times, $current, $order, $dineIn) {
            $detail = match ($step) {
                OrderStatus::Pending => 'We have your order and the kitchen is confirming it.',
                OrderStatus::Preparing => 'The chef is cooking your food.',
                OrderStatus::Ready => $dineIn ? 'Your food is ready and on its way to your table.' : 'Your food is ready.',
                OrderStatus::OutForDelivery => 'Your driver is on the way.',
                OrderStatus::Completed => $dineIn ? 'Enjoy your meal!' : 'All done. Thank you!',
                default => '',
            };

            $state = $current === false ? 'todo' : ($i < $current || ($i === $current && $step === OrderStatus::Completed) ? 'done' : ($i === $current ? 'current' : 'todo'));

            return [
                'key' => $step->value,
                'label' => $step->label(),
                'detail' => $detail,
                'at' => $times->get($step->value)?->format('g:i A'),
                'state' => $state,
            ];
        })->values()->all();
    }

    private function scene(Order $order): ?array
    {
        $floor = Floor::orderBy('sort_order')->with(['tables' => fn ($q) => $q->where('is_active', true)])->first();
        if (! $floor) {
            return null;
        }

        $table = $order->reservation?->table;

        return [
            'floors' => [[
                'id' => $floor->id, 'name' => $floor->name, 'width' => $floor->width, 'height' => $floor->height,
                'outline' => $floor->outline, 'zones' => $floor->zones ?? [],
                'tables' => $floor->tables->map(fn ($t) => $t->only(['id', 'label', 'seats', 'shape', 'x', 'y', 'w', 'h', 'is_vip']))->values(),
            ]],
            'tableId' => $table?->id,
            'tableLabel' => $table?->label,
        ];
    }
}
