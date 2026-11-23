<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $r->code }}</title>
    <style>
        @page { margin: 28px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1c1917; }
        h1 { margin: 0; text-align: center; font-size: 17px; letter-spacing: 3px; text-transform: uppercase; }
        .sub { text-align: center; color: #78716c; font-size: 9px; margin: 3px 0 14px; }
        .bar { border-top: 3px solid #c9a45c; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 0; vertical-align: top; }
        td.r { text-align: right; }
        .muted { color: #78716c; }
        .rule { border-top: 1px dashed #a8a29e; margin: 10px 0; }
        .total td { font-weight: bold; font-size: 13px; padding-top: 6px; }
        .stamp { text-align: center; font-weight: bold; letter-spacing: 3px; margin-top: 12px; font-size: 12px; }
        .paid { color: #047857; }
        .due { color: #b45309; }
        .foot { text-align: center; color: #78716c; font-size: 8.5px; margin-top: 14px; }
    </style>
</head>
<body>
    <div class="bar"></div>
    <h1>{{ config('app.name') }}</h1>
    <p class="sub">{{ config('kusina.contact.address') }}@if (config('kusina.contact.phone')) · {{ config('kusina.contact.phone') }}@endif</p>

    <table>
        <tr><td class="muted">Receipt for</td><td class="r"><strong>{{ $r->code }}</strong></td></tr>
        <tr><td class="muted">Issued</td><td class="r">{{ $r->created_at->format('M j, Y g:i A') }}</td></tr>
        <tr><td class="muted">Guest</td><td class="r">{{ $r->customer_name }}</td></tr>
        <tr><td class="muted">Phone</td><td class="r">{{ $r->customer_phone }}</td></tr>
        @if ($r->customer_email)<tr><td class="muted">Email</td><td class="r">{{ $r->customer_email }}</td></tr>@endif
    </table>
    <div class="rule"></div>
    <table>
        <tr><td class="muted">Table</td><td class="r"><strong>{{ $r->table->label }}</strong> · {{ $r->table->floor->name }}@if ($r->table->is_vip) · ★ VIP @endif</td></tr>
        <tr><td class="muted">Seats</td><td class="r">{{ $r->table->seats }}</td></tr>
        <tr><td class="muted">Date</td><td class="r">{{ $r->starts_at->format('D, M j, Y') }}</td></tr>
        <tr><td class="muted">Time</td><td class="r">{{ $r->starts_at->format('g:i A') }} – {{ $r->ends_at->format('g:i A') }}</td></tr>
        <tr><td class="muted">Guests</td><td class="r">{{ $r->party_size }}</td></tr>
        <tr><td class="muted">Status</td><td class="r">{{ ucfirst(str_replace('_', ' ', $r->status)) }}</td></tr>
    </table>
    <div class="rule"></div>
    <table>
        <tr><td>Reservation deposit<br><span class="muted">{{ $r->party_size }} × ${{ number_format(config('kusina.reservations.fee_per_guest'), 2) }}</span></td><td class="r">${{ number_format($r->subtotal, 2) }}</td></tr>
        <tr><td>{{ config('kusina.tax_label') }}</td><td class="r">${{ number_format($r->tax, 2) }}</td></tr>
        <tr class="total"><td>Total ({{ config('kusina.currency') }})</td><td class="r">${{ number_format($r->total, 2) }}</td></tr>
        <tr><td class="muted">Payment</td><td class="r">{{ $r->paymentLabel() }}</td></tr>
        @if ($r->payment_account)<tr><td class="muted">Account</td><td class="r">{{ $r->payment_account }}</td></tr>@endif
        @if ($r->payment_ref)<tr><td class="muted">Reference</td><td class="r">{{ $r->payment_ref }}</td></tr>@endif
    </table>
    <p class="stamp {{ $r->payment_status === 'paid' ? 'paid' : 'due' }}">{{ $r->payment_status === 'paid' ? 'PAID' : 'DUE ON ARRIVAL' }}</p>
    <div class="rule"></div>
    <p class="foot">Demo system: no real payment was taken.<br>Thank you for choosing {{ config('app.name') }}.</p>
</body>
</html>
