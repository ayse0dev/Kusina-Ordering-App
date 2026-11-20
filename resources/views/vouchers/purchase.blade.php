<x-kusina-layout title="Your gift voucher">
<section class="bg-white">
    <div class="max-w-xl mx-auto px-4 py-16" x-data="voucherPurchase(@js(['token' => $voucher->public_token, 'status' => $voucher->status]))">

        {{-- Waiting for the payment provider to confirm --}}
        <div x-show="status === 'pending'" class="text-center">
            <p class="script text-4xl">Almost there…</p>
            <p class="mt-3 text-navy-600">We’re confirming your payment. This page updates automatically.</p>
        </div>

        <div x-show="status === 'void'" x-cloak class="text-center">
            <h1 class="text-2xl font-bold">Payment not completed</h1>
            <p class="mt-3 text-navy-600">No voucher was issued and you haven’t been charged. <a href="{{ route('vouchers.index') }}" class="link-gold-dark">Try again</a></p>
        </div>

        @if ($voucher->status === 'active')
            <div x-show="status === 'active'" class="text-center">
                <p class="script text-5xl">Salamat po!</p>
                <h1 class="mt-1 text-2xl font-bold">Your gift voucher</h1>

                <div class="mt-8 hero-ambient text-white p-8 border border-sun-400/50 print:border-black">
                    <x-sinag class="w-10 h-10 text-sun-400 mx-auto" />
                    <p class="font-display text-[10px] uppercase tracking-[0.4em] text-sun-300 mt-3">Kusina gift voucher</p>
                    <p class="font-display text-5xl font-bold mt-2">${{ number_format($voucher->value, 2) }} <span class="text-sm font-normal text-white/60">CAD</span></p>
                    <p class="mt-5 font-display text-2xl font-bold tracking-[0.18em] text-sun-300 select-all">{{ $voucher->code }}</p>
                    @if ($voucher->recipient_name)<p class="mt-4 text-white/70 text-sm">For {{ $voucher->recipient_name }}@if ($voucher->buyer_name), from {{ $voucher->buyer_name }}@endif</p>@endif
                    @if ($voucher->message)<p class="mt-2 italic text-white/80">“{{ $voucher->message }}”</p>@endif
                </div>

                <p class="mt-6 text-sm text-navy-500">We’ve emailed the code. Enter it at checkout to use it. Keep it safe: anyone with the code can use the balance.</p>
                <div class="mt-6 flex justify-center gap-8">
                    <a href="{{ route('menu') }}" class="link-gold-dark">Order now</a>
                    <button type="button" x-data @click="window.print()" class="link-gold-dark">Print</button>
                </div>
            </div>
        @endif
    </div>
</section>
</x-kusina-layout>
