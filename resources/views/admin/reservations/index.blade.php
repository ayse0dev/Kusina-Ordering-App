<x-admin-layout title="Reservations">
<div x-data="reservationBoard()">
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <h1 class="text-xl font-semibold">Reservations</h1>
        <form method="GET" class="ml-auto flex items-center gap-2">
            <input type="date" name="date" value="{{ $date }}" class="rounded-lg border-stone-300 text-sm">
            <button class="rounded-lg bg-stone-800 text-white px-3 py-2 text-sm">Go</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-cream-line shadow-card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-stone-500 bg-stone-50">
                <tr><th class="p-3">Time</th><th class="p-3">Guest</th><th class="p-3">Party</th><th class="p-3">Table</th><th class="p-3">Status</th><th class="p-3">Actions</th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($reservations as $r)
                    @php
                        $badge = ['confirmed' => 'bg-emerald-100 text-emerald-700', 'seated' => 'bg-sky-100 text-sky-700', 'completed' => 'bg-stone-100 text-stone-600', 'cancelled' => 'bg-red-100 text-red-700', 'no_show' => 'bg-red-100 text-red-700'][$r->status] ?? 'bg-stone-100';
                        $actions = match ($r->status) {
                            'confirmed' => ['seated' => 'Seat', 'no_show' => 'No-show', 'cancelled' => 'Cancel'],
                            'seated' => ['completed' => 'Complete'],
                            default => [],
                        };
                    @endphp
                    <tr>
                        <td class="p-3 whitespace-nowrap font-medium">{{ $r->starts_at->format('g:i A') }}–{{ $r->ends_at->format('g:i A') }}</td>
                        <td class="p-3">{{ $r->customer_name }}<br><span class="text-xs text-stone-500">{{ $r->customer_phone }} · {{ $r->code }}</span>
                            @if ($r->notes)<br><span class="text-xs text-sun-700">“{{ $r->notes }}”</span>@endif</td>
                        <td class="p-3">{{ $r->party_size }}</td>
                        <td class="p-3">{{ $r->table->label }} <span class="text-xs text-stone-500">({{ $r->table->floor->name }})</span> @if ($r->table->is_vip)<span class="ml-1 inline-block bg-sun-400 text-navy-950 text-[10px] font-bold tracking-widest uppercase px-1.5 py-0.5 align-middle">★ VIP</span>@endif</td>
                        <td class="p-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $r->status)) }}</span></td>
                        <td class="p-3">
                            <div class="flex gap-1.5">
                                @foreach ($actions as $status => $label)
                                    <form method="POST" action="{{ route('admin.reservations.status', $r) }}">
                                        @csrf <input type="hidden" name="status" value="{{ $status }}">
                                        <button class="rounded-lg border px-2.5 py-1 text-xs hover:bg-stone-50 {{ in_array($status, ['cancelled', 'no_show']) ? 'text-red-600 border-red-200' : '' }}">{{ $label }}</button>
                                    </form>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-stone-500">No reservations for this date.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</x-admin-layout>
