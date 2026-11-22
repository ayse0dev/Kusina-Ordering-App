<?php

namespace App\Http\Controllers;

use App\Enums\OrderType;
use App\Services\CartService;
use App\Services\DeliveryService;
use App\Services\SimulatedPayment;
use App\Services\OrderService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function show(CartService $cart, PricingService $pricing)
    {
        $summary = $cart->summary();

        if (! $summary['lines']) {
            return redirect()->route('menu')->with('notice', 'Your cart is empty.');
        }

        return view('checkout.show', [
            'summary' => $summary,
            'quote' => $this->payload($pricing->quote($summary['subtotal'], OrderType::Pickup)),
            'taxLabel' => config('kusina.tax_label'),
            'onlineEnabled' => (bool) config('kusina.stripe.secret'),
        ]);
    }

    public function quote(Request $request, CartService $cart, PricingService $pricing, DeliveryService $delivery)
    {
        $data = $request->validate([
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'delivery_address' => ['nullable', 'string', 'max:300'],
            'codes' => ['array', 'max:4'],
            'codes.*' => ['string', 'max:40'],
        ]);

        $type = OrderType::from($data['order_type']);
        $info = null;
        $addressError = null;

        if ($type === OrderType::Delivery && mb_strlen(trim($data['delivery_address'] ?? '')) >= 8) {
            try {
                $info = $delivery->quote($data['delivery_address']);
            } catch (ValidationException $e) {
                $addressError = $e->errors()['delivery_address'][0] ?? 'We cannot deliver to that address.';
            }
        }

        $q = $pricing->quote($cart->summary()['subtotal'], $type, $data['codes'] ?? [], deliveryFee: $info['fee'] ?? null);

        return response()->json($this->payload($q) + ['delivery' => [
            'eta' => $info['eta'] ?? null,
            'source' => $info['source'] ?? null,
            'message' => $info['message'] ?? null,
            'error' => $addressError,
        ]]);
    }

    private function payload(array $q): array
    {
        return [
            'subtotal' => $q['subtotal'], 'discount' => $q['discount'], 'delivery_fee' => $q['delivery_fee'],
            'tax' => $q['tax'], 'total' => $q['total'], 'gift_applied' => $q['gift_applied'], 'amount_due' => $q['amount_due'],
            'errors' => (object) $q['errors'],
            'applied' => collect([$q['promo'], $q['gift']])->filter()->map(fn ($v) => ['code' => $v->code, 'label' => $v->label()])->values(),
        ];
    }

    public function store(Request $request, OrderService $orders)
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'table_number' => ['nullable', 'required_if:order_type,dine_in', 'string', 'max:20'],
            'delivery_address' => ['nullable', 'required_if:order_type,delivery', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:300'],
            'customer_email' => ['nullable', 'required_if:order_type,dine_in', 'email:rfc', 'max:150'],
            'payment_method' => ['required', Rule::in($request->input('order_type') === 'dine_in' ? array_keys(config('kusina.payment_methods')) : ['counter', 'online'])],
            'codes' => ['array', 'max:4'],
            'codes.*' => ['string', 'max:40'],
        ]);

        if ($request->input('order_type') === 'dine_in') {
            $data += $request->validate(array_diff_key(SimulatedPayment::rules($request->input('payment_method')), ['payment_method' => 1]));
        }

        if ($data['order_type'] === OrderType::Delivery->value) {
            $data['delivery'] = app(DeliveryService::class)->quote($data['delivery_address']);
        }

        [$order, $checkoutUrl] = $orders->place($data);

        return $checkoutUrl
            ? redirect()->away($checkoutUrl)
            : redirect()->route('track', $order->code);
    }
}
