<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index()
    {
        return view('vouchers.index', [
            'presets' => config('kusina.vouchers.gift_presets'),
            'min' => config('kusina.vouchers.gift_min'),
            'max' => config('kusina.vouchers.gift_max'),
            'onlineEnabled' => (bool) config('kusina.stripe.secret'),
        ]);
    }

    public function purchase(Request $request, VoucherService $service)
    {
        $min = config('kusina.vouchers.gift_min');
        $max = config('kusina.vouchers.gift_max');

        $data = $request->validate([
            'amount' => ['required', 'numeric', "min:$min", "max:$max"],
            'buyer_name' => ['required', 'string', 'max:100'],
            'buyer_email' => ['required', 'email', 'max:150'],
            'recipient_name' => ['required', 'string', 'max:100'],
            'recipient_email' => ['nullable', 'email', 'max:150'],
            'message' => ['nullable', 'string', 'max:300'],
        ]);
        $data['amount'] = round((float) $data['amount'], 2);

        [, $url] = $service->startPurchase($data);

        return redirect()->away($url);
    }

    /** Where Stripe sends the buyer back; shows the code once payment is confirmed by webhook. */
    public function show(string $token)
    {
        $voucher = Voucher::where('public_token', $token)->where('kind', 'gift')->firstOrFail();

        return view('vouchers.purchase', compact('voucher'));
    }

    public function status(string $token)
    {
        $voucher = Voucher::where('public_token', $token)->firstOrFail();

        return response()->json(['status' => $voucher->status]);
    }

    /** Public balance check (code is the secret; throttled to prevent guessing). */
    public function check(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $v = Voucher::where('code', Voucher::normalize($data['code']))->first();

        if (! $v || ! $v->isUsable()) {
            return response()->json(['valid' => false, 'message' => 'That code is not valid or has expired.']);
        }

        return response()->json([
            'valid' => true,
            'message' => $v->kind === 'gift'
                ? 'Gift voucher balance: $'.number_format($v->balance, 2).' CAD'
                : 'Discount code: '.$v->label().($v->min_spend ? ' (min. spend $'.number_format($v->min_spend, 2).')' : ''),
        ]);
    }
}
