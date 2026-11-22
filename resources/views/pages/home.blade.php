<x-kusina-layout title="Home">
@php $heroPhoto = file_exists(public_path('images/hero.jpg')); @endphp

{{-- Receiving area --}}
<section class="relative isolate overflow-hidden text-center text-white min-h-[82vh] flex items-center">
    <div class="absolute inset-0 -z-20 hero-ambient"
         @if ($heroPhoto) style="background-image:url('{{ asset('images/hero.jpg') }}');background-size:cover;background-position:center" @endif></div>
    <div class="absolute inset-0 -z-10 bg-gradient-to-b from-black/65 via-black/45 to-black/85"></div>

    <div class="mx-auto max-w-3xl px-4 py-24">
        <div class="mx-auto grid place-items-center w-24 h-24 rounded-full border border-sun-400/80">
            <x-sinag class="w-14 h-14 text-sun-400" />
        </div>
        <p class="mt-4 font-display text-sm font-semibold uppercase tracking-[0.5em] text-sun-300 pl-[0.5em]">Kusi Nna Restaurant</p>
        <p class="font-display text-[10px] uppercase tracking-[0.4em] text-white/60 mt-1">Lutong Pinoy · Edson, Alberta</p>

        <h1 class="mt-10 text-3xl sm:text-5xl font-bold leading-tight">Authentic Filipino dining</h1>
        <p class="mt-3 font-script text-3xl sm:text-4xl text-white/90">“Kain na — a taste of home, served with malasakit.”</p>

        <div class="mt-10 flex flex-wrap items-center justify-center gap-x-10 gap-y-4">
            <a href="{{ route('menu') }}" class="link-gold">Order online</a>
            <a href="{{ route('reservations.index') }}" class="link-gold">Reserve a table</a>
            <a href="{{ route('vouchers.index') }}" class="link-gold">Gift vouchers</a>
        </div>
    </div>
</section>

{{-- Pictures of our food --}}
<section class="bg-white">
    <div class="max-w-6xl mx-auto px-4 py-16">
        <div class="text-center">
            <h2 class="section-title text-3xl">From our kitchen</h2>
            <span class="section-rule mx-auto"></span>
            <p class="script text-4xl mt-3">Mga paborito</p>
        </div>

        @php
            // A varied mosaic: some tiles are larger. `grid-flow-dense` fills any gaps.
            $spans = ['col-span-2 row-span-2', '', 'row-span-2', '', '', 'col-span-2', '', 'row-span-2', '', 'col-span-2', '', ''];
        @endphp

        @if ($photos->isNotEmpty())
            <div class="mt-10" x-data="{ photos: @js($photos), open: null,
                    show(i) { this.open = (i + this.photos.length) % this.photos.length } }"
                 @keydown.escape.window="open = null" @keydown.arrow-right.window="open !== null && show(open + 1)" @keydown.arrow-left.window="open !== null && show(open - 1)">
                <div class="grid grid-cols-2 md:grid-cols-4 auto-rows-[9.5rem] md:auto-rows-[13rem] grid-flow-dense gap-2 md:gap-3">
                    @foreach ($photos as $i => $ph)
                        <button type="button" @click="show({{ $i }})"
                                class="group relative overflow-hidden bg-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-sun-500 {{ $spans[$i % count($spans)] }}"
                                aria-label="View photo: {{ $ph['alt'] }}">
                            <img src="{{ $ph['url'] }}" alt="{{ $ph['alt'] }}" loading="lazy"
                                 class="absolute inset-0 w-full h-full object-cover transition duration-700 group-hover:scale-110">
                            <span class="absolute inset-0 bg-black/0 group-hover:bg-black/25 transition"></span>
                        </button>
                    @endforeach
                </div>

                {{-- Viewer --}}
                <div x-cloak x-show="open !== null" class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4" @click.self="open = null">
                    <button type="button" @click="open = null" class="absolute top-4 right-5 text-3xl text-white/80 hover:text-white" aria-label="Close">✕</button>
                    <button type="button" @click="show(open - 1)" class="absolute left-3 sm:left-6 text-4xl text-white/70 hover:text-sun-300 px-2" aria-label="Previous">‹</button>
                    <template x-if="open !== null">
                        <img :src="photos[open].url" :alt="photos[open].alt" class="max-h-[88vh] max-w-full object-contain">
                    </template>
                    <button type="button" @click="show(open + 1)" class="absolute right-3 sm:right-6 text-4xl text-white/70 hover:text-sun-300 px-2" aria-label="Next">›</button>
                </div>
            </div>
        @else
            {{-- No photos uploaded yet: a quiet placeholder mosaic --}}
            <div class="mt-10 grid grid-cols-2 md:grid-cols-4 auto-rows-[9.5rem] md:auto-rows-[13rem] grid-flow-dense gap-2 md:gap-3" aria-hidden="true">
                @foreach (range(0, 7) as $i)
                    <div class="hero-ambient grid place-items-center border border-sun-400/20 {{ $spans[$i % count($spans)] }}">
                        <x-sinag class="w-10 h-10 text-sun-400/40" />
                    </div>
                @endforeach
            </div>
            @auth
                @if (auth()->user()->isAdmin())
                    <p class="mt-4 text-center text-sm text-navy-500">You’re signed in as admin: <a href="{{ route('admin.gallery') }}" class="link-gold-dark">add your food photos in Admin → Gallery</a>.</p>
                @endif
            @endauth
        @endif

        <p class="text-center mt-10"><a href="{{ route('menu') }}" class="link-gold-dark">See the full menu</a></p>
    </div>
</section>
{{-- Features: a slider. Each slide pairs a feature with a food photo; side buttons bring in the next one. --}}
@php
    $features = [
        ['Authentic recipes', 'Family-style Filipino classics: adobo, sinigang, kare-kare and more, cooked fresh every day.', 'See the menu', route('menu')],
        ['Build it your way', 'Choose sizes, add-ons and special instructions on every dish, with the price updating as you go.', 'Start your order', route('menu')],
        ['Live order tracking', 'Follow your order from the kitchen to your table, pickup counter or door in real time.', null, null],
        ['Reserve your table', 'See our floor plan and pick the exact table you want, for any party size.', 'Reserve a table', route('reservations.index')],
        ['Dine in, pick up or delivery', 'However you like to eat, order online and pay by card or at the counter.', 'Order now', route('menu')],
        ['Gift vouchers & offers', 'Share the taste of home with a gift voucher, or save with a discount code.', 'Gift vouchers', route('vouchers.index')],
    ];
@endphp
<section class="relative bg-navy-950 text-white overflow-hidden pattern-sinag" aria-roledescription="carousel" aria-label="Why Kusi Nna"
         x-data="featureSlider({ count: {{ count($features) }} })"
         @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false"
         @keydown.arrow-right="next()" @keydown.arrow-left="prev()"
         @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)">
    <div class="ribbon"></div>

    <div class="max-w-6xl mx-auto px-4 pt-16 text-center">
        <h2 class="font-display uppercase tracking-[0.12em] font-semibold text-3xl">Why Kusi Nna</h2>
        <span class="block w-14 h-0.5 bg-sun-400 mx-auto mt-2"></span>
        <p class="font-script text-4xl text-sun-300 mt-3">Ang aming handog</p>
    </div>

    <div class="relative max-w-6xl mx-auto">
        {{-- Side buttons --}}
        <button type="button" @click="prev()" aria-label="Previous feature"
                class="absolute left-1 sm:left-3 top-1/2 -translate-y-1/2 z-10 grid place-items-center w-11 h-11 sm:w-12 sm:h-12 rounded-full border border-sun-400/70 bg-navy-950/70 text-sun-300 hover:bg-sun-400 hover:text-navy-950 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-sun-400">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <button type="button" @click="next()" aria-label="Next feature"
                class="absolute right-1 sm:right-3 top-1/2 -translate-y-1/2 z-10 grid place-items-center w-11 h-11 sm:w-12 sm:h-12 rounded-full border border-sun-400/70 bg-navy-950/70 text-sun-300 hover:bg-sun-400 hover:text-navy-950 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-sun-400">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </button>

        <div class="overflow-hidden">
            <div class="flex transition-transform duration-700 ease-out motion-reduce:transition-none"
                 :style="`transform: translateX(-${i * 100}%)`" aria-live="polite">
                @foreach ($features as $n => [$title, $text, $cta, $href])
                    @php $photo = $photos->isNotEmpty() ? $photos[$n % $photos->count()] : null; @endphp
                    <article class="w-full shrink-0 px-14 sm:px-24 py-12 sm:py-16 grid md:grid-cols-2 gap-8 md:gap-14 items-center"
                             role="group" aria-roledescription="slide" aria-label="{{ $n + 1 }} of {{ count($features) }}" :aria-hidden="i !== {{ $n }}">
                        {{-- Text (alternates sides for rhythm) --}}
                        <div class="{{ $n % 2 ? 'md:order-2' : '' }}">
                            <p class="font-display text-xs tracking-[0.3em] text-sun-400">{{ sprintf('%02d', $n + 1) }} <span class="text-white/30">/ {{ sprintf('%02d', count($features)) }}</span></p>
                            <x-sinag class="w-7 h-7 text-sun-400 mt-4" />
                            <h3 class="mt-4 font-display font-bold uppercase tracking-[0.14em] text-2xl sm:text-3xl text-white">{{ $title }}</h3>
                            <span class="block w-12 h-0.5 bg-sun-400 mt-3"></span>
                            <p class="mt-5 text-white/75 font-light leading-relaxed text-base sm:text-lg max-w-md">{{ $text }}</p>
                            @if ($cta)<p class="mt-7"><a href="{{ $href }}" class="link-gold" :tabindex="i === {{ $n }} ? 0 : -1">{{ $cta }}</a></p>@endif
                        </div>

                        {{-- Food picture --}}
                        <div class="relative {{ $n % 2 ? 'md:order-1' : '' }}">
                            <div class="absolute -inset-2 border border-sun-400/40 translate-x-3 translate-y-3 pointer-events-none"></div>
                            <div class="relative aspect-[4/3] overflow-hidden bg-navy-900">
                                @if ($photo)
                                    <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" loading="lazy" class="w-full h-full object-cover">
                                @else
                                    <div class="hero-ambient w-full h-full grid place-items-center"><x-sinag class="w-16 h-16 text-sun-400/40" /></div>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Dots --}}
    <div class="flex justify-center gap-2.5 pb-12" role="tablist" aria-label="Choose a feature">
        @foreach ($features as $n => $f)
            <button type="button" role="tab" @click="go({{ $n }})" :aria-selected="i === {{ $n }}" aria-label="Show {{ $f[0] }}"
                    :class="i === {{ $n }} ? 'bg-sun-400 w-8' : 'bg-white/30 w-2.5 hover:bg-white/60'"
                    class="h-2.5 rounded-full transition-all duration-300"></button>
        @endforeach
    </div>
</section>

{{-- Story teaser + CTA --}}
<section class="bg-white">
    <div class="max-w-3xl mx-auto px-4 py-16 text-center">
        <h2 class="section-title text-3xl">Our story</h2>
        <span class="section-rule mx-auto"></span>
        <p class="mt-6 italic text-navy-600 text-lg">From a Filipino family table to the heart of Alberta.</p>
        <p class="mt-4 text-navy-500 leading-relaxed">Kusi Nna is about carrying the flavours, and the warmth, of the Philippines across an ocean and sharing them with our neighbours in Canada.</p>
        <p class="mt-8"><a href="{{ route('about') }}" class="link-gold-dark">Read our story</a></p>
    </div>
</section>
</x-kusina-layout>
