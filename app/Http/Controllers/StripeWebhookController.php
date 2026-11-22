<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Voucher;
use App\Services\OrderService;
use App\Services\StripeService;
use App\Services\VoucherService;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeService $stripe, OrderService $orders, VoucherService $vouchers)
    {
        if (! $stripe->verifySignature($request->getContent(), $request->header('Stripe-Signature'))) {
            abort(403, 'Invalid signature');
        }

        $type = $request->json('type');
        $session = $request->json('data.object', []);
        $sessionId = $session['id'] ?? '__none__';

        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
            && ($session['payment_status'] ?? '') === 'paid') {
            $paidCents = (int) ($session['amount_total'] ?? 0);

            if ($order = Order::where('stripe_session_id', $sessionId)->first()) {
                if ($paidCents >= (int) round($order->amount_due * 100)) {
                    $orders->markPaid($order);
                }
            } elseif ($voucher = Voucher::where('stripe_session_id', $sessionId)->first()) {
                if ($paidCents >= (int) round($voucher->value * 100)) {
                    $vouchers->activatePurchase($voucher);
                }
            }
        }

        if ($type === 'checkout.session.expired') {
            if ($order = Order::where('stripe_session_id', $sessionId)->first()) {
                $orders->abandon($order);
            } elseif ($voucher = Voucher::where('stripe_session_id', $sessionId)->where('status', 'pending')->first()) {
                $voucher->update(['status' => 'void']);
            }
        }

        return response()->json(['received' => true]);
    }
}
