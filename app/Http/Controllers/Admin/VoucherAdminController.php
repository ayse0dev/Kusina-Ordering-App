<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherAdminController extends Controller
{
    public function index()
    {
        return view('admin.vouchers.index', [
            'discounts' => Voucher::where('kind', 'discount')->latest()->get(),
            'gifts' => Voucher::where('kind', 'gift')->where('status', '!=', 'void')->latest()->limit(100)->get(),
        ]);
    }

    public function storeDiscount(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[A-Za-z0-9\-]+$/'],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'min_spend' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'description' => ['nullable', 'string', 'max:150'],
        ]);

        $data['code'] = Voucher::normalize($data['code']);
        if (Voucher::where('code', $data['code'])->exists()) {
            return back()->withErrors(['code' => 'That code already exists.'])->withInput();
        }
        if ($data['type'] === 'percent' && $data['value'] > 100) {
            return back()->withErrors(['value' => 'A percentage cannot exceed 100.'])->withInput();
        }

        Voucher::create($data + ['kind' => 'discount', 'status' => 'active']);

        return back()->with('status', 'Discount code created.');
    }

    /** Issue a gift voucher directly (e.g. a goodwill gesture or an in-store sale). */
    public function storeGift(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:1000'],
            'recipient_name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:150'],
        ]);

        $v = Voucher::create([
            'code' => Voucher::generateGiftCode(), 'kind' => 'gift', 'status' => 'active', 'value' => $data['amount'],
            'balance' => $data['amount'], 'recipient_name' => $data['recipient_name'] ?? null,
            'description' => $data['description'] ?? 'Issued by staff', 'paid_at' => now(),
        ]);

        return back()->with('status', "Gift voucher issued: {$v->code}");
    }

    public function toggle(Voucher $voucher)
    {
        abort_if($voucher->status === 'pending', 422);
        $voucher->update(['status' => $voucher->status === 'active' ? 'void' : 'active']);

        return back();
    }
}
