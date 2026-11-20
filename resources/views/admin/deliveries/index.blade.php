<x-admin-layout title="Deliveries">
    <h1 class="text-xl font-bold mb-1">Deliveries &amp; couriers</h1>
    <p class="text-sm text-navy-500 mb-5">Choose the courier with <code class="bg-cream-dark px-1">KUSINA_COURIER</code> in <code class="bg-cream-dark px-1">.env</code> (manual, uber or grab).</p>

    <div class="grid gap-3 sm:grid-cols-3 mb-6">
        @foreach ($providers as $p)
            @php
                $labels = ['manual' => ['Own driver', 'Flat fee. Staff assign the driver.'], 'uber' => ['Uber Direct', 'Available in Canada. Live quotes and tracking.'], 'grab' => ['GrabExpress', 'Southeast Asia only (not Canada).']];
            @endphp
            <div class="bg-white border {{ $p['selected'] ? 'border-sun-500 shadow-card' : 'border-cream-line' }} p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="font-display text-xs font-bold uppercase tracking-[0.18em]">{{ $labels[$p['key']][0] }}</p>
                    @if ($p['selected'])<span class="text-[10px] font-bold uppercase tracking-widest bg-sun-400 text-navy-950 px-1.5 py-0.5">Selected</span>@endif
                </div>
                <p class="text-xs text-navy-500 mt-1">{{ $labels[$p['key']][1] }}</p>
                <p class="mt-2 text-xs {{ $p['configured'] ? 'text-emerald-700' : 'text-brand-700' }}">{{ $p['configured'] ? '✓ Credentials set' : '✕ Missing credentials / pickup details' }}</p>
            </div>
        @endforeach
    </div>

    @if ($configuredKey !== $activeKey)
        <div class="mb-4 bg-sun-50 border border-sun-200 text-sun-800 px-4 py-3 text-sm">
            <strong>{{ $configuredKey }}</strong> is selected but not fully configured, so new deliveries are using <strong>{{ $activeKey }}</strong> (your own driver).
        </div>
    @endif
    @unless ($pickupSet)
        <div class="mb-4 bg-sun-50 border border-sun-200 text-sun-800 px-4 py-3 text-sm">Set <code>KUSINA_PICKUP_ADDRESS</code> (and ideally <code>KUSINA_PICKUP_LAT</code> / <code>KUSINA_PICKUP_LNG</code>) so couriers know where to collect orders.</div>
    @endunless

    <div class="bg-white border border-cream-line shadow-card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-navy-500 bg-cream">
                <tr><th class="p-3">Order</th><th class="p-3">Courier</th><th class="p-3">Status</th><th class="p-3">Driver</th><th class="p-3">Fee</th><th class="p-3">Booked</th><th class="p-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-cream-line">
                @forelse ($deliveries as $d)
                    <tr>
                        <td class="p-3 font-bold">{{ $d->order->code }}<br><span class="text-xs font-normal text-navy-400">{{ \Illuminate\Support\Str::limit($d->order->delivery_address, 40) }}</span></td>
                        <td class="p-3 capitalize">{{ $d->provider }}</td>
                        <td class="p-3">{{ $d->status->label() }}@if ($d->failure_reason)<br><span class="text-xs text-brand-700">{{ $d->failure_reason }}</span>@endif</td>
                        <td class="p-3">{{ $d->courier_name ?: '—' }}@if ($d->courier_phone)<br><span class="text-xs text-navy-400">{{ $d->courier_phone }}</span>@endif</td>
                        <td class="p-3">${{ number_format($d->quoted_fee, 2) }}<br><span class="text-xs text-navy-400">{{ $d->fee_source === 'courier' ? 'live quote' : 'flat' }}</span></td>
                        <td class="p-3 whitespace-nowrap">{{ $d->created_at->format('M j, g:i A') }}</td>
                        <td class="p-3">@if ($d->tracking_url)<a href="{{ $d->tracking_url }}" target="_blank" rel="noopener noreferrer" class="underline text-sun-700">Track</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-8 text-center text-navy-400">No delivery orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $deliveries->links() }}</div>
</x-admin-layout>
