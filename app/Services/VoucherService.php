<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class VoucherService
{
    public function __construct(private StripeService $stripe) {}

    public function redeem(Order $order, array $quote): void
    {
        if ($quote['promo']) {
            $quote['promo']->increment('uses');
            VoucherRedemption::create(['voucher_id' => $quote['promo']->id, 'order_id' => $order->id, 'amount' => $quote['discount']]);
        }

        if ($quote['gift'] && $quote['gift_applied'] > 0) {
            $quote['gift']->update(['balance' => round((float) $quote['gift']->balance - $quote['gift_applied'], 2)]);
            VoucherRedemption::create(['voucher_id' => $quote['gift']->id, 'order_id' => $order->id, 'amount' => $quote['gift_applied']]);
        }
    }

    public function restore(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach (VoucherRedemption::where('order_id', $order->id)->with('voucher')->lockForUpdate()->get() as $r) {
                $v = $r->voucher;
                if ($v->kind === 'gift') {
                    $v->update(['balance' => min((float) $v->value, round((float) $v->balance + (float) $r->amount, 2))]);
                } elseif ($v->uses > 0) {
                    $v->decrement('uses');
                }
                $r->delete();
            }
        });
    }

    public function startPurchase(array $data): array
    {
        $voucher = Voucher::create([
            'code' => Voucher::generateGiftCode(),
            'kind' => 'gift',
            'status' => 'pending',
            'value' => $data['amount'],
            'balance' => $data['amount'],
            'buyer_name' => $data['buyer_name'],
            'buyer_email' => $data['buyer_email'],
            'recipient_name' => $data['recipient_name'],
            'recipient_email' => $data['recipient_email'] ?? null,
            'message' => $data['message'] ?? null,
            'public_token' => Str::random(40),
        ]);

        try {
            [$sessionId, $url] = $this->stripe->createCheckout(
                (float) $data['amount'],
                'Kusi Nna gift voucher',
                'gift-'.$voucher->id,
                route('vouchers.purchase', $voucher->public_token),
                route('vouchers.index'),
            );
            $voucher->update(['stripe_session_id' => $sessionId]);
        } catch (Throwable $e) {
            report($e);
            $voucher->update(['status' => 'void']);
            throw ValidationException::withMessages(['amount' => 'Online payment is unavailable right now. Please try again later.']);
        }

        return [$voucher, $url];
    }

    public function activatePurchase(Voucher $voucher): void
    {
        if ($voucher->status !== 'pending') {
            return;
        }

        $voucher->update(['status' => 'active', 'paid_at' => now()]);

        foreach (array_filter([$voucher->recipient_email, $voucher->buyer_email]) as $to) {
            try {
                Mail::raw($this->emailBody($voucher), fn ($m) => $m->to($to)->subject('Your Kusi Nna gift voucher'));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function emailBody(Voucher $v): string
    {
        return "Hello {$v->recipient_name},\n\n{$v->buyer_name} sent you a Kusi Nna gift voucher worth $"
            .number_format($v->value, 2)." CAD.\n\n".($v->message ? "\"{$v->message}\"\n\n" : '')
            ."Your code: {$v->code}\n\nEnter it at checkout to use it. Salamat po!\n";
    }
}
