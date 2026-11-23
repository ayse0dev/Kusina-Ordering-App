<x-kusina-layout title="Checkout">
<div class="max-w-5xl mx-auto px-4 py-10 grid gap-8 lg:grid-cols-5"
     x-data="checkoutPage(@js(['type' => old('order_type', 'pickup'), 'pay' => old('payment_method', 'counter'), 'quote' => $quote, 'address' => old('delivery_address', ''), 'reservation' => old('reservation_code', request('reservation', ''))]))">

    <form method="POST" action="{{ route('checkout.store') }}" class="lg:col-span-3 space-y-6 bg-white border border-cream-line shadow-card p-6">
        @csrf
        <h1 class="text-2xl font-bold">Checkout</h1>

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                <ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div>
            <p class="font-display text-xs font-bold uppercase tracking-[0.2em] mb-2">How would you like your order?</p>
            <div class="grid grid-cols-3 gap-2">
                @foreach (\App\Enums\OrderType::cases() as $t)
                    <label class="cursor-pointer">
                        <input type="radio" name="order_type" value="{{ $t->value }}" x-model="type" class="sr-only peer">
                        <div class="text-center border border-cream-line px-2 py-3 text-sm peer-checked:border-sun-500 peer-checked:bg-sun-50 peer-checked:font-bold hover:bg-cream">{{ $t->label() }}</div>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-sm font-bold" for="customer_name">Name</label>
                <input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" required maxlength="100" class="mt-1 w-full">
            </div>
            <div>
                <label class="text-sm font-bold" for="customer_phone">Phone number</label>
                <input id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}" required inputmode="tel" maxlength="20" placeholder="(780) 555-0123" class="mt-1 w-full">
            </div>
        </div>

        <div>
            <label class="text-sm font-bold" for="customer_email">Email <span class="text-navy-400 font-normal" x-text="type === 'dine_in' ? '' : '(optional, for your receipt)'"></span></label>
            <input id="customer_email" name="customer_email" type="email" value="{{ old('customer_email', auth()->user()?->email) }}" maxlength="150" autocomplete="email" :required="type === 'dine_in'" placeholder="you@example.com" class="mt-1 w-full">
            <p class="mt-1 text-xs text-navy-500">We email your receipt here.</p>
        </div>

        <div x-show="type === 'dine_in'" x-cloak>
            <label class="text-sm font-bold" for="reservation_code">Reservation code</label>
            <input id="reservation_code" name="reservation_code" x-model="reservation" maxlength="20" placeholder="RSV-XXXXXXX" autocomplete="off"
                   :required="type === 'dine_in'" :disabled="type !== 'dine_in'" class="mt-1 w-full uppercase tracking-wider">
            <div x-show="bookings.length" x-cloak class="mt-2">
                <p class="text-xs text-navy-500">Your bookings on this device:</p>
                <div class="mt-1 flex flex-wrap gap-2">
                    <template x-for="b in bookings" :key="b.code">
                        <button type="button" @click="reservation = b.code" class="border px-2.5 py-1.5 text-xs transition hover:bg-cream"
                                :class="reservation === b.code ? 'border-sun-500 bg-sun-50 font-bold' : 'border-cream-line'"
                                x-text="`${b.code} · Table ${b.table || '?'} · ${b.when || ''}`"></button>
                    </template>
                </div>
            </div>
            <p class="mt-1 text-xs text-navy-500">Dine-in orders go to your booked table, so book a table first. You can order from 15 minutes before your booking until it ends.
                <a href="{{ route('reservations.index') }}" class="text-sun-700 underline">Book a table</a></p>
        </div>

        <div x-show="type === 'delivery'" x-cloak>
            <label class="text-sm font-bold" for="delivery_address">Delivery address</label>
            <textarea id="delivery_address" name="delivery_address" rows="3" maxlength="300" x-model.debounce.800ms="address" :required="type === 'delivery'" :disabled="type !== 'delivery'" placeholder="Street address, city, postal code" class="mt-1 w-full"></textarea>
            <p x-show="quote.delivery?.error" x-text="quote.delivery?.error" x-cloak class="mt-1 text-sm text-brand-700"></p>
            <p x-show="!quote.delivery?.error && quote.delivery?.eta" x-cloak class="mt-1 text-xs text-navy-500">Estimated delivery about <strong x-text="quote.delivery?.eta"></strong> min · <span x-text="quote.delivery?.source === 'courier' ? 'live courier price' : 'standard delivery fee'"></span></p>
            <p x-show="quote.delivery?.message" x-text="quote.delivery?.message" x-cloak class="mt-1 text-xs bg-sun-50 border border-sun-200 text-sun-800 px-3 py-1.5"></p>
        </div>

        <div>
            <label class="text-sm font-bold" for="notes">Order notes <span class="text-navy-400 font-normal">(optional)</span></label>
            <textarea id="notes" name="notes" rows="2" maxlength="300" class="mt-1 w-full">{{ old('notes') }}</textarea>
        </div>

        <div>
            <label class="font-display text-xs font-bold uppercase tracking-[0.2em]" for="code">Voucher or discount code</label>
            <div class="mt-2 flex gap-2">
                <input id="code" x-model="codeInput" @keydown.enter.prevent="addCode()" maxlength="40" placeholder="Enter code" class="flex-1 uppercase tracking-wider">
                <button type="button" @click="addCode()" :disabled="busy" class="border border-navy-950 px-5 font-display text-xs font-bold uppercase tracking-[0.2em] hover:bg-navy-950 hover:text-sun-300 transition">Apply</button>
            </div>
            <p x-show="codeMsg" x-text="codeMsg" class="mt-2 text-sm text-brand-700"></p>
            <div class="mt-2 flex flex-wrap gap-2">
                <template x-for="a in quote.applied" :key="a.code">
                    <span class="inline-flex items-center gap-2 bg-sun-50 border border-sun-200 px-3 py-1 text-sm">
                        <strong x-text="a.code"></strong><span class="text-sun-800" x-text="a.label"></span>
                        <button type="button" @click="removeCode(a.code)" class="text-navy-400 hover:text-brand-700" aria-label="Remove code">✕</button>
                    </span>
                </template>
            </div>
            <template x-for="c in codes" :key="c"><input type="hidden" name="codes[]" :value="c"></template>
        </div>

        <div>
            <p class="font-display text-xs font-bold uppercase tracking-[0.2em] mb-2">Payment</p>
            <p x-show="quote.amount_due <= 0" x-cloak class="bg-sun-50 border border-sun-200 text-sun-800 text-sm px-4 py-3">Your voucher covers the full amount. Nothing to pay.</p>
            <div x-show="quote.amount_due > 0 && type === 'dine_in'" x-cloak>
                <x-payment-fields model="pay" active="type === 'dine_in' && quote.amount_due > 0" />
            </div>
            <div class="grid sm:grid-cols-2 gap-2" x-show="quote.amount_due > 0 && type !== 'dine_in'">
                <label class="cursor-pointer">
                    <input type="radio" name="payment_method" value="counter" x-model="pay" :disabled="type === 'dine_in'" class="sr-only peer">
                    <div class="border border-cream-line px-3 py-3 peer-checked:border-sun-500 peer-checked:bg-sun-50 hover:bg-cream">
                        <p class="text-sm font-bold">Pay at counter</p>
                        <p class="text-xs text-navy-500">Cash or card when you pick up, dine in, or receive delivery.</p>
                    </div>
                </label>
                <label class="{{ $onlineEnabled ? 'cursor-pointer' : 'opacity-50 cursor-not-allowed' }}">
                    <input type="radio" name="payment_method" value="online" x-model="pay" :disabled="type === 'dine_in'" class="sr-only peer" @disabled(! $onlineEnabled)>
                    <div class="border border-cream-line px-3 py-3 peer-checked:border-sun-500 peer-checked:bg-sun-50 hover:bg-cream">
                        <p class="text-sm font-bold">Pay online</p>
                        <p class="text-xs text-navy-500">{{ $onlineEnabled ? 'Credit or debit card via Stripe.' : 'Currently unavailable.' }}</p>
                    </div>
                </label>
            </div>
            <template x-if="quote.amount_due <= 0"><input type="hidden" name="payment_method" :value="type === 'dine_in' ? 'cash' : 'counter'"></template>
        </div>

        <button class="btn-primary w-full" :disabled="!!quote.delivery?.error && type === 'delivery'"
                x-text="quote.amount_due <= 0 ? 'Place order' : (type !== 'dine_in' && pay === 'online' ? 'Continue to payment' : (type === 'dine_in' && pay !== 'cash' ? 'Pay & place order' : 'Place order'))"></button>
    </form>

    <aside class="lg:col-span-2 bg-white border border-cream-line shadow-card p-6 h-fit">
        <h2 class="font-display text-xs font-bold uppercase tracking-[0.2em] mb-3">Order summary</h2>
        <ul class="divide-y divide-cream-line text-sm">
            @foreach ($summary['lines'] as $l)
                <li class="py-2.5 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        @if (!empty($l['image_url']))
                            <img src="{{ $l['image_url'] }}" alt="{{ $l['name'] }}" class="w-12 h-12 rounded-lg object-cover border border-cream-line shrink-0 shadow-sm">
                        @endif
                        <div class="min-w-0">
                            <p class="truncate"><span class="font-bold">{{ $l['qty'] }}×</span> {{ $l['name'] }}</p>
                            @if ($l['options'])<p class="text-xs text-navy-500 truncate">{{ collect($l['options'])->pluck('name')->join(' · ') }}</p>@endif
                        </div>
                    </div>
                    <span class="font-semibold shrink-0">${{ number_format($l['line_total'], 2) }}</span>
                </li>
            @endforeach
        </ul>
        <dl class="mt-3 pt-3 border-t border-cream-line text-sm space-y-1.5">
            <div class="flex justify-between"><dt class="text-navy-500">Subtotal</dt><dd x-text="money(quote.subtotal)"></dd></div>
            <div class="flex justify-between text-emerald-700" x-show="quote.discount > 0" x-cloak><dt>Discount</dt><dd x-text="'−' + money(quote.discount)"></dd></div>
            <div class="flex justify-between" x-show="quote.delivery_fee > 0" x-cloak><dt class="text-navy-500">Delivery fee</dt><dd x-text="money(quote.delivery_fee)"></dd></div>
            <div class="flex justify-between"><dt class="text-navy-500">{{ $taxLabel }}</dt><dd x-text="money(quote.tax)"></dd></div>
            <div class="flex justify-between font-bold pt-1 border-t border-cream-line"><dt>Total</dt><dd x-text="money(quote.total)"></dd></div>
            <div class="flex justify-between text-emerald-700" x-show="quote.gift_applied > 0" x-cloak><dt>Gift voucher</dt><dd x-text="'−' + money(quote.gift_applied)"></dd></div>
            <div class="flex justify-between items-baseline pt-2 border-t border-cream-line" x-show="quote.gift_applied > 0" x-cloak><dt class="font-display text-xs font-bold uppercase tracking-[0.2em]">Amount due</dt><dd class="font-display text-xl font-bold" x-text="money(quote.amount_due)"></dd></div>
        </dl>
    </aside>
</div>
</x-kusina-layout>
