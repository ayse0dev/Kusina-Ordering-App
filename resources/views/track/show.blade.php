@php
    $steps = collect(\App\Enums\OrderStatus::timeline($order->order_type))->map(fn ($s) => ['key' => $s->value, 'label' => $s->label()])->values();
@endphp
<x-kusina-layout title="Order {{ $order->code }}">
<div class="max-w-2xl mx-auto px-4 py-8"
     x-data="trackPage(@js(['code' => $order->code, 'status' => $order->status->value, 'payment' => $order->payment_status, 'steps' => $steps, 'delivery' => $delivery]))">

    <div class="bg-white rounded-2xl border border-cream-line shadow-card p-6">
        <p class="text-sm text-stone-500">Order number</p>
        <p class="text-2xl font-bold tracking-wide">{{ $order->code }}</p>
        <p class="text-sm text-stone-500 mt-1">{{ $order->order_type->label() }} · {{ $order->created_at->format('M j, g:i A') }}</p>

        {{-- Payment notices --}}
        @if ($order->payment_method === 'online')
            <div x-show="payment !== 'paid' && status !== 'cancelled'" x-cloak class="mt-4 rounded-lg bg-sun-50 border border-sun-200 text-sun-800 text-sm px-4 py-2">
                Waiting for payment confirmation… this page will update automatically.
            </div>
        @else
            @if ($order->amount_due > 0)<p class="mt-4 text-sm text-stone-600">Please pay at the counter. Amount due: <strong>${{ number_format($order->amount_due, 2) }}</strong></p>@else<p class="mt-4 text-sm text-emerald-700">Paid in full with your voucher. Thank you!</p>@endif
        @endif

        <div x-show="status === 'cancelled'" x-cloak class="mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-2 text-sm">This order was cancelled.</div>

        {{-- Delivery / courier --}}
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

        {{-- Timeline --}}
        <ol class="mt-6 space-y-0" x-show="status !== 'cancelled'">
            <template x-for="(s, i) in steps" :key="s.key">
                <li class="flex gap-3">
                    <div class="flex flex-col items-center">
                        <span class="w-6 h-6 rounded-full grid place-items-center text-xs font-bold"
                              :class="i < idx ? 'bg-emerald-500 text-white' : (i === idx ? 'bg-brand-600 text-white ring-4 ring-brand-100' : 'bg-stone-200 text-stone-500')"
                              x-text="i < idx ? '✓' : i + 1"></span>
                        <span class="w-0.5 flex-1 min-h-6" :class="i < idx ? 'bg-emerald-400' : 'bg-stone-200'" x-show="i < steps.length - 1"></span>
                    </div>
                    <p class="pb-5" :class="i === idx ? 'font-semibold' : (i < idx ? 'text-stone-600' : 'text-stone-400')" x-text="s.label"></p>
                </li>
            </template>
        </ol>
    </div>

    <div class="mt-5 bg-white rounded-2xl border border-cream-line shadow-card p-6">
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
    <p class="text-center mt-5"><a href="{{ route('menu') }}" class="text-brand-600 hover:underline text-sm">← Back to menu</a></p>
</div>
</x-kusina-layout>
