<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $o->code }}</title>
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
        <tr><td class="muted">Order</td><td class="r"><strong>{{ $o->code }}</strong></td></tr>
        <tr><td class="muted">Date</td><td class="r">{{ $o->created_at->format('M j, Y g:i A') }}</td></tr>
        <tr><td class="muted">Customer</td><td class="r">{{ $o->customer_name }}</td></tr>
        <tr><td class="muted">Type</td><td class="r">{{ $o->order_type->label() }}@if ($o->table_number) · Table {{ $o->table_number }}@endif</td></tr>
    </table>
    <div class="rule"></div>
    <table>
        @foreach ($o->items as $i)
            <tr><td>{{ $i->qty }}× {{ $i->name }}@if ($i->options->isNotEmpty())<br><span class="muted">{{ $i->options->pluck('name')->join(' · ') }}</span>@endif</td><td class="r">${{ number_format($i->line_total, 2) }}</td></tr>
        @endforeach
    </table>
    <div class="rule"></div>
    <table>
        <tr><td>Subtotal</td><td class="r">${{ number_format($o->subtotal, 2) }}</td></tr>
        @if ($o->discount > 0)<tr><td>Discount</td><td class="r">−${{ number_format($o->discount, 2) }}</td></tr>@endif
        @if ($o->gift_applied > 0)<tr><td>Gift voucher</td><td class="r">−${{ number_format($o->gift_applied, 2) }}</td></tr>@endif
        @if ($o->delivery_fee > 0)<tr><td>Delivery</td><td class="r">${{ number_format($o->delivery_fee, 2) }}</td></tr>@endif
        <tr><td>{{ config('kusina.tax_label') }}</td><td class="r">${{ number_format($o->tax, 2) }}</td></tr>
        <tr class="total"><td>Total ({{ config('kusina.currency') }})</td><td class="r">${{ number_format($o->total, 2) }}</td></tr>
        <tr><td class="muted">Payment</td><td class="r">{{ config("kusina.payment_methods.{$o->payment_method}.label", ucfirst($o->payment_method)) }}@if ($o->payment_detail) ({{ $o->payment_detail }})@endif</td></tr>
        @if ($o->payment_account)<tr><td class="muted">Account</td><td class="r">{{ $o->payment_account }}</td></tr>@endif
        @if ($o->payment_ref)<tr><td class="muted">Reference</td><td class="r">{{ $o->payment_ref }}</td></tr>@endif
    </table>
    <p class="stamp {{ $o->payment_status === 'paid' ? 'paid' : 'due' }}">{{ $o->payment_status === 'paid' ? 'PAID' : 'PAY AT THE RESTAURANT' }}</p>
    <div class="rule"></div>
    <p class="foot">Demo system: no real payment was taken.<br>Thank you for choosing {{ config('app.name') }}.</p>
</body>
</html>
