<!doctype html>
<html><body style="margin:0;background:#f4efe6;font-family:Arial,Helvetica,sans-serif;color:#1c1917">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border:1px solid #e7dfce">
<tr><td style="background:#0b0b0b;color:#e6c35c;padding:20px 24px;font-size:20px;letter-spacing:2px;text-transform:uppercase">{{ config('app.name') }}</td></tr>
<tr><td style="padding:24px">
    <p style="margin:0 0 4px;font-size:18px"><strong>Reservation confirmed, {{ $r->customer_name }}.</strong></p>
    <p style="margin:0 0 16px;color:#57534e">Show this code when you arrive: <strong style="letter-spacing:1px">{{ $r->code }}</strong></p>
    <table width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;border-top:1px solid #e7dfce">
        <tr><td style="color:#78716c">Table</td><td><strong>{{ $r->table->label }}</strong> · {{ $r->table->floor->name }} · seats {{ $r->table->seats }}@if ($r->table->is_vip) · ★ VIP @endif</td></tr>
        <tr><td style="color:#78716c">Date</td><td>{{ $r->starts_at->format('l, F j, Y') }}</td></tr>
        <tr><td style="color:#78716c">Time</td><td>{{ $r->starts_at->format('g:i A') }} – {{ $r->ends_at->format('g:i A') }}</td></tr>
        <tr><td style="color:#78716c">Guests</td><td>{{ $r->party_size }}</td></tr>
        @if ($r->notes)<tr><td style="color:#78716c">Notes</td><td>{{ $r->notes }}</td></tr>@endif
    </table>
    <p style="margin:18px 0 6px;font-weight:bold">Receipt</p>
    <table width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;border-top:1px solid #e7dfce">
        <tr><td>Reservation deposit ({{ $r->party_size }} × ${{ number_format(config('kusina.reservations.fee_per_guest'), 2) }})</td><td align="right">${{ number_format($r->subtotal, 2) }}</td></tr>
        <tr><td>{{ config('kusina.tax_label') }}</td><td align="right">${{ number_format($r->tax, 2) }}</td></tr>
        <tr><td><strong>Total</strong></td><td align="right"><strong>${{ number_format($r->total, 2) }} {{ config('kusina.currency') }}</strong></td></tr>
        <tr><td>Payment</td><td align="right">{{ $r->paymentLabel() }} · {{ $r->payment_status === 'paid' ? 'PAID' : 'Pay on arrival' }}</td></tr>
        @if ($r->payment_ref)<tr><td>Reference</td><td align="right">{{ $r->payment_ref }}</td></tr>@endif
    </table>
    <p style="margin:20px 0 0"><a href="{{ route('reservations.receipt', $r->code) }}" style="background:#e6c35c;color:#0b0b0b;text-decoration:none;padding:10px 18px;font-weight:bold;display:inline-block">View or print your receipt</a></p>
    <p style="margin:18px 0 0;font-size:12px;color:#78716c">Demo system: no real payment was taken. We hold your table for the booked time only.</p>
</td></tr></table>
</td></tr></table>
</body></html>
