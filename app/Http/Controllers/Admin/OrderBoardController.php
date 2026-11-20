<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderBoardController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index()
    {
        return view('admin.orders.board');
    }

    public function feed()
    {
        $orders = Order::visibleToStaff()
            ->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])
            ->with(['items.options', 'delivery'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'code' => $o->code,
                'status' => $o->status->value,
                'next' => $o->status->next($o->order_type)?->value,
                'next_label' => $o->status->next($o->order_type)?->label(),
                'type' => $o->order_type->label(),
                'type_key' => $o->order_type->value,
                'customer' => $o->customer_name,
                'phone' => $o->customer_phone,
                'table' => $o->table_number,
                'address' => $o->delivery_address,
                'notes' => $o->notes,
                'payment_method' => $o->payment_method,
                'payment_status' => $o->payment_status,
                'total' => (float) $o->total,
                'gift_applied' => (float) $o->gift_applied,
                'amount_due' => (float) $o->amount_due,
                'placed' => $o->created_at->diffForHumans(),
                'delivery' => $o->delivery ? [
                    'provider' => $o->delivery->provider,
                    'status' => $o->delivery->status->value,
                    'label' => $o->delivery->status->label(),
                    'courier' => $o->delivery->courier_name,
                    'phone' => $o->delivery->courier_phone,
                    'tracking' => $o->delivery->tracking_url,
                    'eta' => $o->delivery->eta_minutes,
                    'fee_source' => $o->delivery->fee_source,
                    'error' => $o->delivery->failure_reason,
                    'active' => $o->delivery->status->isActive(),
                    'can_request' => in_array($o->delivery->status->value, ['pending', 'failed'], true) && $o->delivery->provider !== 'manual',
                ] : null,
                'items' => $o->items->map(fn ($i) => [
                    'qty' => $i->qty,
                    'name' => $i->name,
                    'notes' => $i->notes,
                    'options' => $i->options->map(fn ($op) => $op->name)->all(),
                ])->all(),
            ]);

        return response()->json(['orders' => $orders]);
    }

    public function advance(Request $request, Order $order)
    {
        $next = $order->status->next($order->order_type);
        abort_unless($next, 422, 'No further status.');

        $this->orders->transition($order, $next, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function cancel(Request $request, Order $order)
    {
        $this->orders->transition($order, OrderStatus::Cancelled, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function markPaid(Order $order)
    {
        $this->orders->markPaid($order);

        return response()->json(['ok' => true]);
    }

    public function history()
    {
        $orders = Order::visibleToStaff()->latest()->paginate(25);

        return view('admin.orders.history', compact('orders'));
    }
}
