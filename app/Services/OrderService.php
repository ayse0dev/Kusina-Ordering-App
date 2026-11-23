<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Events\OrderPlaced;
use App\Events\OrderUpdated;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderService
{
    public function __construct(
        private CartService $cart,
        private StripeService $stripe,
        private PricingService $pricing,
        private VoucherService $vouchers,
        private DeliveryService $deliveries,
    ) {}

    public function place(array $data): array
    {
        $summary = $this->cart->summary();
        if (! $summary['lines']) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $type = OrderType::from($data['order_type']);

        $order = DB::transaction(function () use ($summary, $data, $type) {
            $q = $this->pricing->quote($summary['subtotal'], $type, $data['codes'] ?? [], lock: true, deliveryFee: isset($data['delivery']['fee']) ? (float) $data['delivery']['fee'] : null);
            if ($q['errors']) {
                throw ValidationException::withMessages(['codes' => implode(' ', $q['errors'])]);
            }

            $covered = $q['amount_due'] <= 0;

            $order = Order::create([
                'code' => Order::generateCode(),
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'order_type' => $type,
                'table_number' => $type === OrderType::DineIn ? ($data['table_number'] ?? null) : null,
                'reservation_id' => $type === OrderType::DineIn ? ($data['reservation_id'] ?? null) : null,
                'delivery_address' => $type === OrderType::Delivery ? ($data['delivery_address'] ?? null) : null,
                'notes' => $data['notes'] ?? null,
                'status' => OrderStatus::Pending,
                'customer_email' => $data['customer_email'] ?? null,
            ] + $this->paymentFields($data, $covered) + [
                'subtotal' => $q['subtotal'],
                'discount' => $q['discount'],
                'delivery_fee' => $q['delivery_fee'],
                'tax' => $q['tax'],
                'total' => $q['total'],
                'gift_applied' => $q['gift_applied'],
                'amount_due' => $q['amount_due'],
            ]);

            foreach ($summary['lines'] as $line) {
                $item = $order->items()->create([
                    'menu_item_id' => $line['item_id'],
                    'name' => $line['name'],
                    'unit_price' => $line['unit_price'],
                    'qty' => $line['qty'],
                    'line_total' => $line['line_total'],
                    'notes' => $line['notes'],
                ]);
                foreach ($line['options'] as $o) {
                    $item->options()->create(['group_name' => $o['group'], 'name' => $o['name'], 'price_delta' => $o['price_delta']]);
                }
            }

            $order->statusLogs()->create(['status' => OrderStatus::Pending->value]);
            $this->vouchers->redeem($order, $q);

            if ($type === OrderType::Delivery) {
                $this->deliveries->createFor($order, $data['delivery'] ?? []);
            }

            return $order;
        });

        $checkoutUrl = null;

        if ($order->isAwaitingPayment()) {
            try {
                [$sessionId, $checkoutUrl] = $this->stripe->createCheckout(
                    (float) $order->amount_due,
                    'Kusi Nna order '.$order->code,
                    $order->code,
                    route('track', $order->code),
                    route('track', $order->code),
                );
                $order->update(['stripe_session_id' => $sessionId]);
            } catch (Throwable $e) {
                report($e);
                $this->abandon($order);
                throw ValidationException::withMessages([
                    'payment_method' => 'Online payment is unavailable right now. Please choose pay at counter or try again later.',
                ]);
            }
        } else {
            OrderPlaced::dispatch($order);
            ReceiptMailer::order($order);
        }

        $this->cart->clear();

        return [$order, $checkoutUrl];
    }

    private function paymentFields(array $data, bool $covered): array
    {
        $method = $data['payment_method'];

        if ($covered || ! config("kusina.payment_methods.$method")) {
            return ['payment_method' => $method, 'payment_status' => $covered ? 'paid' : 'unpaid', 'paid_at' => $covered ? now() : null];
        }

        return SimulatedPayment::settle($method, $data);
    }

    public function transition(Order $order, OrderStatus $to, ?int $userId = null): Order
    {
        $allowed = $order->status->next($order->order_type);
        $isCancel = $to === OrderStatus::Cancelled && ! $order->status->isFinal();

        if (! $isCancel && $allowed !== $to) {
            throw ValidationException::withMessages(['status' => 'That status change is not allowed.']);
        }
        if ($order->isAwaitingPayment()) {
            throw ValidationException::withMessages(['status' => 'This order is awaiting online payment.']);
        }

        $order->update(['status' => $to]);
        $order->statusLogs()->create(['status' => $to->value, 'user_id' => $userId]);

        if ($isCancel) {
            $this->vouchers->restore($order);
            $this->deliveries->cancelFor($order);
        } else {
            $this->deliveries->syncManual($order, $to);
            $this->deliveries->dispatchIfDue($order, $to);
        }

        OrderUpdated::dispatch($order);

        return $order;
    }

    public function completeServed(Order $order): void
    {
        if ($order->status !== OrderStatus::Ready || $order->order_type !== OrderType::DineIn) {
            return;
        }

        try {
            $this->transition($order, OrderStatus::Completed);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function sweepServed(): void
    {
        Order::where('order_type', OrderType::DineIn->value)
            ->where('status', OrderStatus::Ready->value)
            ->where('updated_at', '<=', now()->subSeconds(config('kusina.serve_seconds')))
            ->get()
            ->each(fn (Order $order) => $this->completeServed($order));
    }

    public function courierUpdate(Order $order, DeliveryStatus $status): void
    {
        $to = match ($status) {
            DeliveryStatus::InTransit => OrderStatus::OutForDelivery,
            DeliveryStatus::Delivered => OrderStatus::Completed,
            default => null,
        };

        if ($to && ! $order->status->isFinal() && $order->status !== $to) {
            $order->update(['status' => $to]);
            $order->statusLogs()->create(['status' => $to->value]);
        }

        OrderUpdated::dispatch($order);
    }

    public function markPaid(Order $order): Order
    {
        if ($order->payment_status === 'paid') {
            return $order;
        }

        $wasHidden = $order->isAwaitingPayment();
        $order->update(['payment_status' => 'paid', 'paid_at' => now()]);

        if ($wasHidden) {
            OrderPlaced::dispatch($order);
        }
        OrderUpdated::dispatch($order);

        return $order;
    }

    public function abandon(Order $order): void
    {
        if ($order->payment_status === 'paid') {
            return;
        }

        $order->update(['status' => OrderStatus::Cancelled, 'payment_status' => 'failed']);
        $this->vouchers->restore($order);
        $this->deliveries->cancelFor($order);
        OrderUpdated::dispatch($order);
    }
}
