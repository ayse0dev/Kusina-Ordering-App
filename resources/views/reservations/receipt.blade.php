@php $r = $reservation; @endphp
<x-receipt-layout title="Receipt {{ $r->code }}" :back="route('reservations.show', $r->code)" :pdf="route('reservations.receipt.pdf', $r->code)">
    <table>
        <tr><td class="muted">Receipt for</td><td><strong>{{ $r->code }}</strong></td></tr>
        <tr><td class="muted">Issued</td><td>{{ $r->created_at->format('M j, Y g:i A') }}</td></tr>
        <tr><td class="muted">Guest</td><td>{{ $r->customer_name }}</td></tr>
        <tr><td class="muted">Phone</td><td>{{ $r->customer_phone }}</td></tr>
        @if ($r->customer_email)<tr><td class="muted">Email</td><td>{{ $r->customer_email }}</td></tr>@endif
    </table>
    <hr class="rule">
    <table>
        <tr><td class="muted">Table</td><td><strong>{{ $r->table->label }}</strong> · {{ $r->table->floor->name }}@if ($r->table->is_vip) · ★ VIP @endif</td></tr>
        <tr><td class="muted">Seats</td><td>{{ $r->table->seats }}</td></tr>
        <tr><td class="muted">Date</td><td>{{ $r->starts_at->format('D, M j, Y') }}</td></tr>
        <tr><td class="muted">Time</td><td>{{ $r->starts_at->format('g:i A') }} – {{ $r->ends_at->format('g:i A') }}</td></tr>
        <tr><td class="muted">Guests</td><td>{{ $r->party_size }}</td></tr>
        <tr><td class="muted">Status</td><td>{{ ucfirst(str_replace('_', ' ', $r->status)) }}</td></tr>
    </table>
    <hr class="rule">
    <table>
        <tr><td>Reservation deposit<br><span class="muted">{{ $r->party_size }} × ${{ number_format(config('kusina.reservations.fee_per_guest'), 2) }}</span></td><td>${{ number_format($r->subtotal, 2) }}</td></tr>
        <tr><td>{{ config('kusina.tax_label') }}</td><td>${{ number_format($r->tax, 2) }}</td></tr>
        <tr class="total"><td>Total ({{ config('kusina.currency') }})</td><td>${{ number_format($r->total, 2) }}</td></tr>
        <tr><td class="muted">Payment</td><td>{{ $r->paymentLabel() }}</td></tr>
        @if ($r->payment_account)<tr><td class="muted">Account</td><td>{{ $r->payment_account }}</td></tr>@endif
        @if ($r->payment_ref)<tr><td class="muted">Reference</td><td>{{ $r->payment_ref }}</td></tr>@endif
    </table>
    <p class="stamp {{ $r->payment_status === 'paid' ? 'paid' : 'due' }}">{{ $r->payment_status === 'paid' ? 'PAID' : 'DUE ON ARRIVAL' }}</p>
</x-receipt-layout>
