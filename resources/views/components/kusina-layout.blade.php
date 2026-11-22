<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="reverb-key" content="{{ config('reverb.apps.apps.0.key') }}">
    <title>{{ $title ?? 'Kusi Nna Restaurant' }} · Kusi Nna Restaurant</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=lato:300,400,700|josefin-sans:400,600,700|great-vibes:400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-cream text-navy-700 antialiased min-h-screen flex flex-col">

{{-- Top bar --}}
<header class="sticky top-0 z-30 bg-navy-950/95 backdrop-blur text-navy-100">
    <div class="max-w-6xl mx-auto px-4 h-16 grid grid-cols-[auto_1fr_auto] md:grid-cols-[1fr_auto_1fr] items-center gap-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2 w-fit" aria-label="Kusi Nna Restaurant home">
            <x-sinag class="w-6 h-6 text-sun-400" />
            <span class="font-script text-3xl leading-none text-sun-300 pt-1">Kusi Nna</span><span class="hidden sm:inline font-display text-[10px] font-semibold uppercase tracking-[0.25em] text-white/60">Restaurant</span>
        </a>

        <nav class="hidden md:flex items-center gap-7 font-display text-[11px] font-semibold uppercase tracking-[0.22em]">
            @foreach ([['home', 'Home'], ['menu', 'Menu'], ['reservations.index', 'Reservations'], ['about', 'About'], ['vouchers.index', 'Vouchers'], ['contact', 'Contact']] as [$r, $l])
                <a href="{{ route($r) }}" class="py-2 hover:text-sun-300 transition {{ request()->routeIs(str_contains($r, '.') ? explode('.', $r)[0].'.*' : $r) ? 'text-sun-400' : '' }}">{{ $l }}</a>
            @endforeach
        </nav>

        <div class="flex items-center justify-end gap-3">
            <form class="hidden lg:block" x-data="{ code: '' }"
                  @submit.prevent="code.trim() && (window.location = '/track/' + encodeURIComponent(code.trim().toUpperCase()))">
                <input x-model="code" type="text" placeholder="TRACK ORDER" maxlength="20" aria-label="Order number"
                       class="w-36 bg-transparent border-0 border-b border-navy-500 text-[11px] tracking-[0.18em] text-white placeholder-navy-400 focus:border-sun-400 focus:ring-0 py-1.5 px-0 font-display">
            </form>
            <button type="button" @click="$store.cart.open = true" x-data x-init="$store.cart.load()"
                    class="relative inline-flex items-center gap-2 border border-sun-400 px-4 py-2 font-display text-[11px] font-semibold uppercase tracking-[0.2em] text-sun-300 hover:bg-sun-400 hover:text-navy-950 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-sun-400">
                Cart
                <span x-show="$store.cart.count > 0" x-text="$store.cart.count" class="bg-sun-400 text-navy-950 rounded-full text-[10px] font-bold min-w-5 h-5 px-1 grid place-items-center"></span>
            </button>
        </div>
    </div>
    <nav class="md:hidden flex overflow-x-auto border-t border-white/10 font-display text-[11px] font-semibold uppercase tracking-[0.18em]">
        @foreach ([['home', 'Home'], ['menu', 'Menu'], ['reservations.index', 'Reserve'], ['about', 'About'], ['vouchers.index', 'Vouchers'], ['contact', 'Contact']] as [$r, $l])
            <a href="{{ route($r) }}" class="px-4 py-2.5 whitespace-nowrap {{ request()->routeIs(str_contains($r, '.') ? explode('.', $r)[0].'.*' : $r) ? 'text-sun-400' : '' }}">{{ $l }}</a>
        @endforeach
    </nav>
</header>

<main class="flex-1">
    @if (session('notice'))
        <div class="max-w-6xl mx-auto px-4 mt-4"><div class="bg-sun-50 border border-sun-200 text-sun-800 px-4 py-2 text-sm">{{ session('notice') }}</div></div>
    @endif
    {{ $slot }}
</main>

<footer class="pattern-banig text-navy-200 mt-16">
    <div class="ribbon"></div>
    <div class="max-w-6xl mx-auto px-4 py-12 grid gap-8 sm:grid-cols-3 text-sm">
        <div>
            <p class="flex items-center gap-2"><x-sinag class="w-6 h-6 text-sun-400" /><span class="font-script text-4xl text-sun-300 leading-none pt-1">Kusi Nna</span><span class="hidden sm:inline font-display text-[10px] font-semibold uppercase tracking-[0.25em] text-white/60">Restaurant</span></p>
            <p class="mt-3 text-navy-300 font-light">Home-style Filipino cooking, made fresh daily and served with malasakit.</p>
            <p class="mt-2 text-navy-300 font-light">{{ config('kusina.contact.address') }}</p>
        </div>
        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.22em] text-sun-400 mb-3">Opening hours</p>
            <p class="font-light">Every day<br><span class="text-white font-bold">9:00 AM – 10:00 PM</span></p>
        </div>
        <div class="sm:text-right">
            <p class="font-script text-3xl text-sun-300">Salamat po!</p>
            <a href="{{ route('vouchers.index') }}" class="inline-block mt-3 mr-4 font-display text-[11px] uppercase tracking-[0.22em] text-navy-300 hover:text-sun-300">Gift vouchers</a><a href="{{ route('login') }}" class="inline-block mt-3 font-display text-[11px] uppercase tracking-[0.22em] text-navy-400 hover:text-sun-300">Staff login</a>
        </div>
    </div>
    <div class="border-t border-white/10 text-center text-[11px] tracking-wider uppercase text-navy-400 py-4">© {{ date('Y') }} Kusi Nna Restaurant · Edson, Alberta</div>
</footer>

{{-- Cart drawer --}}
<div x-data x-cloak x-show="$store.cart.open" class="fixed inset-0 z-40" @keydown.escape.window="$store.cart.open = false">
    <div class="absolute inset-0 bg-navy-950/60" @click="$store.cart.open = false"></div>
    <aside class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-lift flex flex-col"
           x-transition:enter="transition duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">
        <div class="flex items-center justify-between px-6 h-16 bg-navy-950 text-white">
            <h2 class="font-display text-sm font-semibold uppercase tracking-[0.22em]">Your order</h2>
            <button @click="$store.cart.open = false" class="p-1 text-navy-300 hover:text-white" aria-label="Close">✕</button>
        </div>
        <div class="ribbon"></div>

        <div class="flex-1 overflow-y-auto px-6 py-3 divide-y divide-cream-line">
            <template x-if="$store.cart.lines.length === 0">
                <div class="py-16 text-center">
                    <p class="font-script text-4xl text-sun-600">Wala pang laman</p>
                    <p class="text-navy-500 text-sm mt-2">Your cart is empty. Add something delicious.</p>
                </div>
            </template>
            <template x-for="l in $store.cart.lines" :key="l.key">
                <div class="py-4">
                    <div class="flex justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-display font-semibold text-navy-900" x-text="l.name"></p>
                            <template x-if="l.options.length">
                                <p class="text-xs text-navy-500" x-text="l.options.map(o => o.name).join(' · ')"></p>
                            </template>
                            <template x-if="l.notes"><p class="text-xs text-navy-400 italic" x-text="'“' + l.notes + '”'"></p></template>
                        </div>
                        <p class="font-bold whitespace-nowrap" x-text="money(l.line_total)"></p>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <div class="inline-flex items-center border border-cream-line">
                            <button class="px-3 py-1 hover:bg-cream" @click="$store.cart.setQty(l.key, l.qty - 1)" aria-label="Decrease">−</button>
                            <span class="px-2 text-sm w-8 text-center" x-text="l.qty"></span>
                            <button class="px-3 py-1 hover:bg-cream" @click="$store.cart.setQty(l.key, l.qty + 1)" aria-label="Increase">+</button>
                        </div>
                        <button class="text-xs uppercase tracking-widest text-navy-400 hover:text-brand-700" @click="$store.cart.remove(l.key)">Remove</button>
                    </div>
                </div>
            </template>
        </div>

        <div class="border-t border-cream-line bg-cream px-6 py-5 space-y-4">
            <div class="flex justify-between items-baseline"><span class="font-display text-xs uppercase tracking-[0.2em] text-navy-500">Subtotal</span><span class="font-display text-2xl font-bold text-navy-900" x-text="money($store.cart.subtotal)"></span></div>
            <p x-show="$store.cart.error" x-text="$store.cart.error" class="text-sm text-brand-700"></p>
            <a :href="$store.cart.count ? '{{ route('checkout') }}' : '#'" :class="$store.cart.count ? '' : 'opacity-50 pointer-events-none'"
               class="btn-primary w-full">Checkout</a>
        </div>
    </aside>
</div>
</body>
</html>
