@php
    $dineIn = $order->order_type === \App\Enums\OrderType::DineIn;
    $sceneData = $scene ? ['floors' => $scene['floors'], 'tableId' => $scene['tableId']] : null;
@endphp
<x-kusina-layout title="Order {{ $order->code }}">
<div class="max-w-6xl mx-auto px-4 py-8" data-no-reveal
     x-data="trackPage(@js(['code' => $order->code, 'status' => $order->status->value, 'payment' => $order->payment_status, 'steps' => $milestones, 'delivery' => $delivery, 'prep' => $progress, 'scene3d' => $sceneData, 'dineIn' => $dineIn]))">

  <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_380px] items-start">
    <div class="space-y-5 min-w-0">

    <div class="bg-white rounded-2xl border border-cream-line shadow-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm text-stone-500">Order number</p>
                <p class="text-2xl font-bold tracking-wide">{{ $order->code }}</p>
                <p class="text-sm text-stone-500 mt-1">{{ $order->order_type->label() }}@if ($order->table_number) · Table {{ $order->table_number }}@endif · {{ $order->created_at->format('M j, g:i A') }}</p>
            </div>
            <a href="{{ route('track.receipt', $order->code) }}" target="_blank" class="text-sm text-brand-700 hover:underline">🖨 View / print receipt</a>
        </div>

        @if ($order->payment_method === 'online')
            <div x-show="payment !== 'paid' && status !== 'cancelled'" x-cloak class="mt-4 rounded-lg bg-sun-50 border border-sun-200 text-sun-800 text-sm px-4 py-2">
                Waiting for payment confirmation… this page will update automatically.
            </div>
        @elseif ($order->payment_status === 'paid' && $order->payment_ref)
            <p class="mt-4 text-sm text-emerald-700">Paid with {{ config("kusina.payment_methods.{$order->payment_method}.label", ucfirst($order->payment_method)) }}@if ($order->payment_detail) ({{ $order->payment_detail }})@endif · ref {{ $order->payment_ref }}. Thank you!</p>
        @else
            @if ($order->amount_due > 0)<p class="mt-4 text-sm text-stone-600">Please pay at the counter. Amount due: <strong>${{ number_format($order->amount_due, 2) }}</strong></p>@else<p class="mt-4 text-sm text-emerald-700">Paid in full with your voucher. Thank you!</p>@endif
        @endif

        <div x-show="status === 'cancelled'" x-cloak class="mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-2 text-sm">This order was cancelled.</div>

        <template x-if="delivery">
            <div class="mt-5 border border-cream-line bg-cream p-4 text-sm">
                <p class="font-display text-[11px] font-bold uppercase tracking-[0.2em] text-navy-900">Delivery</p>
                <p class="mt-1 font-bold" x-text="delivery.label"></p>
                <p class="mt-1 text-navy-600" x-show="delivery.courier">Your driver: <strong x-text="delivery.courier"></strong>
                    <a x-show="delivery.phone" :href="'tel:' + delivery.phone" class="ml-2 text-sun-700 hover:underline">Call</a></p>
                <p class="mt-1 text-xs text-navy-500" x-show="delivery.eta && delivery.status !== 'delivered'">Estimated delivery time: about <span x-text="delivery.eta"></span> minutes from when your order is ready.</p>
                <p class="mt-2" x-show="delivery.tracking"><a :href="delivery.tracking" target="_blank" rel="noopener noreferrer" class="link-gold-dark">Track your driver live</a></p>
            </div>
        </template>
    </div>

    @if ($scene)
        <div class="bg-navy-950 border border-cream-line shadow-card overflow-hidden" x-show="!fail3d" x-cloak>
            <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                <p class="font-display text-[11px] font-bold uppercase tracking-[0.2em] text-sun-300">Live kitchen view</p>
                <p class="text-xs text-white/60">@if ($scene['tableLabel'])Your table {{ $scene['tableLabel'] }} is highlighted · @endif Drag to look around</p>
            </div>
            <div class="relative h-80 sm:h-[30rem] lg:h-[36rem]">
                <div x-ref="stage" class="absolute inset-0"></div>
                <p x-show="loading3d" class="absolute inset-0 grid place-items-center text-sm text-white/70">Loading the kitchen…</p>
            </div>
        </div>
    @endif

    </div>

    <div class="space-y-5">

    <div class="bg-white rounded-2xl border border-cream-line shadow-card p-6" x-show="status !== 'cancelled'" x-cloak>
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-bold uppercase tracking-[0.2em] text-navy-900">Cooking time</p>
                <p class="mt-1 text-4xl font-bold tabular-nums text-navy-900" x-text="headline"></p>
            </div>
            <p class="text-3xl font-bold tabular-nums text-emerald-600" x-text="Math.round(percent) + '%'"></p>
        </div>
        <div class="mt-3 h-4 rounded-full bg-stone-200 overflow-hidden">
            <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-green-600 transition-[width] duration-500" :style="`width:${percent}%`"></div>
        </div>
        <p class="mt-2 text-sm text-stone-600" x-text="note"></p>
    </div>

    <div class="bg-white rounded-2xl border border-cream-line shadow-card p-6" x-show="status !== 'cancelled'" x-cloak>
        <h2 class="font-semibold mb-4">Order progress</h2>
        <ol>
            <template x-for="(s, i) in steps" :key="s.key">
                <li class="flex gap-4">
                    <div class="flex flex-col items-center">
                        <span class="w-8 h-8 rounded-full grid place-items-center text-sm font-bold ring-4 transition"
                              :class="{ 'bg-emerald-500 text-white ring-emerald-100': s.state === 'done', 'bg-sun-500 text-white ring-sun-200 animate-pulse': s.state === 'current', 'bg-stone-200 text-stone-500 ring-transparent': s.state === 'todo' }"
                              x-text="s.state === 'done' ? '✓' : i + 1"></span>
                        <span class="w-0.5 flex-1 min-h-8 transition" :class="s.state === 'done' ? 'bg-emerald-400' : 'bg-stone-200'" x-show="i < steps.length - 1"></span>
                    </div>
                    <div class="pb-6 flex-1">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="font-semibold" :class="s.state === 'todo' ? 'text-stone-400' : 'text-navy-900'" x-text="s.label"></p>
                            <p class="text-xs text-stone-500 tabular-nums" x-text="s.at || ''"></p>
                        </div>
                        <p class="text-sm text-stone-500" x-show="s.state === 'current'" x-text="s.detail"></p>
                    </div>
                </li>
            </template>
        </ol>
    </div>

    <div class="bg-white rounded-2xl border border-cream-line shadow-card p-6">
        <h2 class="font-semibold mb-3">Your items</h2>
        <ul class="divide-y text-sm">
            @foreach ($order->items as $i)
                <li class="py-2 flex justify-between gap-3">
                    <div>
                        <p><span class="font-medium">{{ $i->qty }}×</span> {{ $i->name }}</p>
                        @if ($i->options->isNotEmpty())<p class="text-xs text-stone-500">{{ $i->options->pluck('name')->join(' · ') }}</p>@endif
                        @if ($i->notes)<p class="text-xs text-stone-400 italic">“{{ $i->notes }}”</p>@endif
                    </div>
                    <span>${{ number_format($i->line_total, 2) }}</span>
                </li>
            @endforeach
        </ul>
        <dl class="mt-3 pt-3 border-t text-sm space-y-1">
            <div class="flex justify-between"><dt class="text-stone-500">Subtotal</dt><dd>${{ number_format($order->subtotal, 2) }}</dd></div>
            @if ($order->discount > 0)<div class="flex justify-between text-emerald-700"><dt>Discount</dt><dd>−${{ number_format($order->discount, 2) }}</dd></div>@endif
            @if ($order->delivery_fee > 0)<div class="flex justify-between"><dt class="text-stone-500">Delivery fee</dt><dd>${{ number_format($order->delivery_fee, 2) }}</dd></div>@endif
            <div class="flex justify-between"><dt class="text-stone-500">{{ config('kusina.tax_label') }}</dt><dd>${{ number_format($order->tax, 2) }}</dd></div>
            <div class="flex justify-between font-semibold text-base"><dt>Total</dt><dd>${{ number_format($order->total, 2) }}</dd></div>
            @if ($order->gift_applied > 0)
                <div class="flex justify-between text-emerald-700"><dt>Gift voucher</dt><dd>−${{ number_format($order->gift_applied, 2) }}</dd></div>
                <div class="flex justify-between font-bold"><dt>Amount due</dt><dd>${{ number_format($order->amount_due, 2) }}</dd></div>
            @endif
        </dl>
    </div>
    </div>
  </div>

    <p class="mt-6 text-center"><a href="{{ route('menu') }}" class="text-brand-600 hover:underline text-sm">← Back to menu</a></p>
</div>
</x-kusina-layout>
