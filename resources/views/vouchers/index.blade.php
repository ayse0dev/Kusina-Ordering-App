<x-kusina-layout title="Vouchers">
<section class="hero-ambient text-center text-white py-16">
    <p class="script text-5xl !text-sun-300">Regalo</p>
    <h1 class="mt-1 text-3xl sm:text-4xl font-bold">Vouchers</h1>
    <p class="mt-3 text-white/70 max-w-xl mx-auto px-4">Share the taste of home with a gift voucher, or check a code you’ve received.</p>
</section>

<section class="bg-white">
    <div class="max-w-5xl mx-auto px-4 py-14 grid gap-12 md:grid-cols-5">

        {{-- Buy a gift voucher --}}
        <div class="md:col-span-3" x-data="giftForm(@js(['presets' => $presets, 'min' => $min, 'max' => $max, 'amount' => old('amount', $presets[1] ?? 50)]))">
            <h2 class="section-title text-xl">Buy a gift voucher</h2><span class="section-rule"></span>
            <p class="text-sm text-navy-500 mt-3">Delivered by email with a unique code. Redeem it at checkout toward any order, and use it over several visits until the balance runs out. Amounts are in CAD.</p>

            @if ($errors->any())
                <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
            @unless ($onlineEnabled)
                <div class="mt-4 bg-sun-50 border border-sun-200 text-sun-800 px-4 py-3 text-sm">Online gift voucher sales are not available yet. Please check back soon, or ask us in the restaurant.</div>
            @endunless

            <form method="POST" action="{{ route('vouchers.start') }}" class="mt-6 space-y-5">
                @csrf
                <div>
                    <p class="text-sm font-bold mb-2">Amount</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="p in presets" :key="p">
                            <button type="button" @click="amount = p"
                                    :class="Number(amount) === p ? 'bg-navy-950 text-sun-300 border-navy-950' : 'bg-white text-navy-800 border-cream-line hover:border-sun-500'"
                                    class="border px-5 py-2.5 font-display font-bold text-sm" x-text="'$' + p"></button>
                        </template>
                        <div class="flex items-center border border-cream-line">
                            <span class="pl-3 text-navy-500">$</span>
                            <input type="number" name="amount" x-model="amount" :min="min" :max="max" step="1" required class="w-24 border-0 focus:ring-0 focus:border-0" aria-label="Amount in CAD">
                        </div>
                    </div>
                    <p class="text-xs text-navy-400 mt-1" x-text="`Between $${min} and $${max}`"></p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="text-sm font-bold" for="buyer_name">Your name</label><input id="buyer_name" name="buyer_name" value="{{ old('buyer_name') }}" required maxlength="100" class="mt-1 w-full"></div>
                    <div><label class="text-sm font-bold" for="buyer_email">Your email</label><input id="buyer_email" type="email" name="buyer_email" value="{{ old('buyer_email') }}" required maxlength="150" class="mt-1 w-full"></div>
                    <div><label class="text-sm font-bold" for="recipient_name">Recipient’s name</label><input id="recipient_name" name="recipient_name" value="{{ old('recipient_name') }}" required maxlength="100" class="mt-1 w-full"></div>
                    <div><label class="text-sm font-bold" for="recipient_email">Recipient’s email <span class="font-normal text-navy-400">(optional)</span></label><input id="recipient_email" type="email" name="recipient_email" value="{{ old('recipient_email') }}" maxlength="150" class="mt-1 w-full"></div>
                </div>
                <div><label class="text-sm font-bold" for="message">Personal message <span class="font-normal text-navy-400">(optional)</span></label>
                    <textarea id="message" name="message" rows="3" maxlength="300" class="mt-1 w-full">{{ old('message') }}</textarea></div>

                <button class="btn-primary" @disabled(! $onlineEnabled)>Continue to payment</button>
                <p class="text-xs text-navy-400">Secure card payment by Stripe. If no recipient email is given, the code is sent to you.</p>
            </form>
        </div>

        {{-- Check / use a code --}}
        <aside class="md:col-span-2 space-y-8">
            <div x-data="voucherCheck()">
                <h2 class="section-title text-xl">Check a code</h2><span class="section-rule"></span>
                <form @submit.prevent="check()" class="mt-4 flex gap-2">
                    <input x-model="code" required maxlength="40" placeholder="GIFT-XXXX-XXXX" class="flex-1 uppercase tracking-wider" aria-label="Voucher code">
                    <button class="btn-primary !px-4" :disabled="busy">Check</button>
                </form>
                <p x-show="msg" x-text="msg" class="mt-3 text-sm" :class="ok ? 'text-emerald-700' : 'text-brand-700'"></p>
            </div>

            <div class="border border-cream-line p-5">
                <h3 class="font-display text-xs font-bold uppercase tracking-[0.2em] text-navy-900">How to use a voucher</h3>
                <ol class="mt-3 space-y-2 text-sm text-navy-600 list-decimal list-inside">
                    <li>Add dishes to your cart and go to checkout.</li>
                    <li>Enter your code under “Voucher or discount code”.</li>
                    <li>Gift vouchers pay toward your total; discount codes reduce the food price.</li>
                </ol>
                <p class="text-xs text-navy-400 mt-3">One discount code and one gift voucher can be combined per order.</p>
            </div>
        </aside>
    </div>
</section>
</x-kusina-layout>
