<x-kusina-layout title="Order not found">
<div class="max-w-lg mx-auto px-4 py-16 text-center">
    <div class="bg-white border border-cream-line shadow-card p-8">
        <p class="font-display text-xs font-bold uppercase tracking-[0.3em] text-sun-600">Not found</p>
        <h1 class="mt-2 text-2xl font-bold">We couldn’t find that order</h1>
        <p class="mt-3 text-sm text-stone-600">Order codes look like <strong>KUS-XXXXXXXX</strong> and are on your order confirmation and email. Reservation codes start with <strong>RSV-</strong> and are opened from your booking.</p>
        <form class="mt-6 flex gap-2" x-data="{ code: '' }" @submit.prevent="code.trim() && (window.location = '/track/' + encodeURIComponent(code.trim().toUpperCase()))">
            <input x-model="code" placeholder="KUS-XXXXXXXX or RSV-XXXXXXX" maxlength="20" class="flex-1 uppercase tracking-wider">
            <button class="btn-primary">Find</button>
        </form>
        <p class="mt-5 text-sm"><a href="{{ route('menu') }}" class="text-brand-600 hover:underline">← Back to menu</a></p>
    </div>
</div>
</x-kusina-layout>
