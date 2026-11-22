@php $o = $order; @endphp
<x-receipt-layout title="Receipt {{ $o->code }}" :back="route('track', $o->code)">
    <table>
        <tr><td class="muted">Order</td><td><strong>{{ $o->code }}</strong></td></tr>
        <tr><td class="muted">Date</td><td>{{ $o->created_at->format('M j, Y g:i A') }}</td></tr>
        <tr><td class="muted">Customer</td><td>{{ $o->customer_name }}</td></tr>
        <tr><td class="muted">Type</td><td>{{ $o->order_type->label() }}@if ($o->table_number) · Table {{ $o->table_number }}@endif</td></tr>
    </table>
    <hr class="rule">
    <table>
        @foreach ($o->items as $i)
            <tr><td>{{ $i->qty }}× {{ $i->name }}@if ($i->options->isNotEmpty())<br><span class="muted">{{ $i->options->pluck('name')->join(' · ') }}</span>@endif</td><td>${{ number_format($i->line_total, 2) }}</td></tr>
        @endforeach
    </table>
    <hr class="rule">
    <table>
        <tr><td>Subtotal</td><td>${{ number_format($o->subtotal, 2) }}</td></tr>
        @if ($o->discount > 0)<tr><td>Discount</td><td>−${{ number_format($o->discount, 2) }}</td></tr>@endif
        @if ($o->gift_applied > 0)<tr><td>Gift voucher</td><td>−${{ number_format($o->gift_applied, 2) }}</td></tr>@endif
        @if ($o->delivery_fee > 0)<tr><td>Delivery</td><td>${{ number_format($o->delivery_fee, 2) }}</td></tr>@endif
        <tr><td>{{ config('kusina.tax_label') }}</td><td>${{ number_format($o->tax, 2) }}</td></tr>
        <tr class="total"><td>Total ({{ config('kusina.currency') }})</td><td>${{ number_format($o->total, 2) }}</td></tr>
        <tr><td class="muted">Payment</td><td>{{ config("kusina.payment_methods.{$o->payment_method}.label", ucfirst($o->payment_method)) }}@if ($o->payment_detail) ({{ $o->payment_detail }})@endif</td></tr>
        @if ($o->payment_account)<tr><td class="muted">Account</td><td>{{ $o->payment_account }}</td></tr>@endif
        @if ($o->payment_ref)<tr><td class="muted">Reference</td><td>{{ $o->payment_ref }}</td></tr>@endif
    </table>
    <p class="stamp {{ $o->payment_status === 'paid' ? 'paid' : 'due' }}">{{ $o->payment_status === 'paid' ? 'PAID' : 'PAY AT THE RESTAURANT' }}</p>
</x-receipt-layout>
