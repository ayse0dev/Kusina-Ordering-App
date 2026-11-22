<!doctype html>
<html><body style="margin:0;background:#f4efe6;font-family:Arial,Helvetica,sans-serif;color:#1c1917">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border:1px solid #e7dfce">
<tr><td style="background:#0b0b0b;color:#e6c35c;padding:20px 24px;font-size:20px;letter-spacing:2px;text-transform:uppercase">{{ config('app.name') }}</td></tr>
<tr><td style="padding:24px">
    <p style="margin:0 0 4px;font-size:18px"><strong>Thanks {{ $o->customer_name }}, we have your order.</strong></p>
    <p style="margin:0 0 16px;color:#57534e">Order <strong>{{ $o->code }}</strong> · {{ $o->order_type->label() }}@if ($o->table_number) · Table {{ $o->table_number }}@endif</p>
    <table width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;border-top:1px solid #e7dfce">
        @foreach ($o->items as $i)
            <tr><td>{{ $i->qty }}× {{ $i->name }}@if ($i->options->isNotEmpty())<br><span style="color:#78716c;font-size:12px">{{ $i->options->pluck('name')->join(' · ') }}</span>@endif</td><td align="right" valign="top">${{ number_format($i->line_total, 2) }}</td></tr>
        @endforeach
    </table>
    <table width="100%" cellpadding="4" cellspacing="0" style="font-size:14px;border-top:1px solid #e7dfce;margin-top:6px">
        <tr><td>Subtotal</td><td align="right">${{ number_format($o->subtotal, 2) }}</td></tr>
        @if ($o->discount > 0)<tr><td>Discount</td><td align="right">−${{ number_format($o->discount, 2) }}</td></tr>@endif
        @if ($o->delivery_fee > 0)<tr><td>Delivery</td><td align="right">${{ number_format($o->delivery_fee, 2) }}</td></tr>@endif
        <tr><td>{{ config('kusina.tax_label') }}</td><td align="right">${{ number_format($o->tax, 2) }}</td></tr>
        <tr><td><strong>Total</strong></td><td align="right"><strong>${{ number_format($o->total, 2) }} {{ config('kusina.currency') }}</strong></td></tr>
        <tr><td>Payment</td><td align="right">{{ config("kusina.payment_methods.{$o->payment_method}.label", ucfirst($o->payment_method)) }}@if ($o->payment_detail) ({{ $o->payment_detail }})@endif · {{ $o->payment_status === 'paid' ? 'PAID' : 'Pay at the restaurant' }}</td></tr>
        @if ($o->payment_ref)<tr><td>Reference</td><td align="right">{{ $o->payment_ref }}</td></tr>@endif
    </table>
    <p style="margin:20px 0 0"><a href="{{ route('track.receipt', $o->code) }}" style="background:#e6c35c;color:#0b0b0b;text-decoration:none;padding:10px 18px;font-weight:bold;display:inline-block">View or print your receipt</a></p>
    <p style="margin:18px 0 0;font-size:12px;color:#78716c">Demo system: no real payment was taken.</p>
</td></tr></table>
</td></tr></table>
</body></html>
