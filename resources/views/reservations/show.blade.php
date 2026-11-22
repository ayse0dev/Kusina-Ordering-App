<x-kusina-layout title="Reservation {{ $reservation->code }}">
<div class="max-w-lg mx-auto px-4 py-8">
    <div class="bg-white rounded-2xl border border-cream-line shadow-card p-6 space-y-4">
        <div>
            <p class="text-sm text-stone-500">Reservation code</p>
            <p class="text-2xl font-bold tracking-wide">{{ $reservation->code }}</p>
            <p class="text-xs text-stone-500">Keep this code to view or cancel your booking.</p>
        </div>

        @php
            $badge = ['confirmed' => 'bg-emerald-100 text-emerald-700', 'seated' => 'bg-sky-100 text-sky-700', 'completed' => 'bg-stone-100 text-stone-600', 'cancelled' => 'bg-red-100 text-red-700', 'no_show' => 'bg-red-100 text-red-700'][$reservation->status] ?? 'bg-stone-100';
        @endphp
        <span class="inline-block rounded-full px-3 py-1 text-xs font-medium {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $reservation->status)) }}</span>

        <dl class="text-sm grid grid-cols-3 gap-y-2">
            <dt class="text-stone-500">Name</dt><dd class="col-span-2">{{ $reservation->customer_name }}</dd>
            <dt class="text-stone-500">Date</dt><dd class="col-span-2">{{ $reservation->starts_at->format('l, F j, Y') }}</dd>
            <dt class="text-stone-500">Time</dt><dd class="col-span-2">{{ $reservation->starts_at->format('g:i A') }} – {{ $reservation->ends_at->format('g:i A') }}</dd>
            <dt class="text-stone-500">Table</dt><dd class="col-span-2">{{ $reservation->table->label }} ({{ $reservation->table->floor->name }}), seats {{ $reservation->table->seats }} @if ($reservation->table->is_vip)<span class="ml-1 inline-block bg-sun-400 text-navy-950 text-[10px] font-bold tracking-widest uppercase px-1.5 py-0.5 align-middle">★ VIP</span>@endif</dd>
            <dt class="text-stone-500">Guests</dt><dd class="col-span-2">{{ $reservation->party_size }}</dd>
            <dt class="text-stone-500">Email</dt><dd class="col-span-2">{{ $reservation->customer_email }}</dd>
            <dt class="text-stone-500">Payment</dt><dd class="col-span-2">{{ $reservation->paymentLabel() }} ·
                @if ($reservation->payment_status === 'paid')<span class="text-emerald-700 font-medium">Paid ${{ number_format($reservation->total, 2) }}</span>@else<span class="text-amber-700 font-medium">${{ number_format($reservation->total, 2) }} due on arrival</span>@endif</dd>
            @if ($reservation->notes)<dt class="text-stone-500">Notes</dt><dd class="col-span-2">{{ $reservation->notes }}</dd>@endif
        </dl>

        <a href="{{ route('reservations.receipt', $reservation->code) }}" target="_blank" class="inline-block border border-navy-950 px-4 py-2 text-sm font-semibold hover:bg-navy-950 hover:text-sun-300 transition">🖨 View / print receipt</a>
        @if ($reservation->customer_email)<p class="text-xs text-stone-500">A copy was emailed to {{ $reservation->customer_email }}.</p>@endif

        @if ($reservation->status === 'confirmed' && $reservation->starts_at->isFuture())
            <form method="POST" action="{{ route('reservations.cancel', $reservation->code) }}" x-data @submit="if (!confirm('Cancel this reservation?')) $event.preventDefault()">
                @csrf
                <button class="text-sm text-red-600 hover:underline">Cancel reservation</button>
            </form>
        @endif
    </div>
    <p class="text-center mt-5"><a href="{{ route('menu') }}" class="text-brand-600 hover:underline text-sm">← Back to menu</a></p>
</div>
</x-kusina-layout>
