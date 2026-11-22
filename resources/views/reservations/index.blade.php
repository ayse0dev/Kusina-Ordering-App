<x-kusina-layout title="Reserve a table">
<div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-6" x-data="reservationPage(@js(['floors' => $floors, 'config' => $config]))">
    <h1 class="text-xl font-semibold">Reserve a table</h1>
    <p class="text-sm text-stone-500 mb-5">Choose your date, time and party size on the right, then pick your table on the map. Open daily {{ date('g A', strtotime($config['open'])) }}–{{ date('g A', strtotime($config['close'])) }}; each booking holds the table for {{ $config['minutes'] }} minutes.</p>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px] items-start">

        <div class="bg-white border border-cream-line shadow-card p-4 min-w-0">
            <div class="flex flex-wrap items-center gap-3 mb-3">
                <div class="inline-flex border border-cream-line font-display text-[11px] font-semibold uppercase tracking-[0.18em]" role="tablist" aria-label="View">
                    <button type="button" role="tab" :aria-selected="view === '3d'" @click="view = '3d'"
                            :class="view === '3d' ? 'bg-navy-950 text-sun-300' : 'text-navy-600 hover:bg-cream'" class="px-4 py-2 transition">3D building</button>
                    <button type="button" role="tab" :aria-selected="view === '2d'" @click="view = '2d'"
                            :class="view === '2d' ? 'bg-navy-950 text-sun-300' : 'text-navy-600 hover:bg-cream'" class="px-4 py-2 transition">Floor plan</button>
                </div>
                <div class="ml-auto flex flex-wrap gap-3 text-xs text-navy-600">
                    <span class="flex items-center gap-1.5"><i class="w-3 h-3 bg-emerald-200 border border-emerald-500"></i>Available</span>
                    <span class="flex items-center gap-1.5"><i class="w-3 h-3 bg-red-600"></i>Booked / occupied</span>
                    <span class="flex items-center gap-1.5"><i class="w-3 h-3 bg-stone-500"></i>Too small</span>
                    <span class="flex items-center gap-1.5"><i class="w-3 h-3 bg-green-600"></i>Your table</span>
                    <span class="flex items-center gap-1.5" x-show="vipFloor"><i class="w-3 h-3 bg-[#5a1624] border border-amber-300"></i>★ VIP</span>
                </div>
            </div>

            <p x-show="!time" class="text-sm text-sun-800 bg-sun-50 border border-sun-200 px-3 py-2 mb-3">Choose a time to see which tables are free.</p>
            <p x-show="fail3d" x-cloak class="text-sm text-navy-600 bg-cream border border-cream-line px-3 py-2 mb-3">Your browser couldn’t start the 3D view, so we’re showing the floor plan instead.</p>

            <div x-show="vipFloor" x-cloak class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 bg-navy-950 text-white border border-sun-400/60 px-4 py-3">
                <p class="font-display text-[11px] font-bold uppercase tracking-[0.22em] text-sun-300">★ <span x-text="config.vip.title"></span></p>
                <p class="text-sm text-white/75 flex-1 min-w-48" x-text="config.vip.blurb"></p>
                <button type="button" @click="showVip()" class="link-gold">Show VIP lounge</button>
            </div>

            <div x-show="view === '3d'">
                <div x-ref="stageWrap"
                     class="relative hero-ambient border border-navy-800 overflow-hidden select-none h-[78vh] min-h-[460px] sm:min-h-[620px] max-h-[980px] [&:fullscreen]:!h-screen [&:fullscreen]:!max-h-none [&:fullscreen]:border-0">
                    <div x-ref="stage" class="absolute inset-0"></div>

                    <div x-show="loading3d" class="absolute inset-0 grid place-items-center text-center text-white/80 pointer-events-none">
                        <div><x-sinag class="w-12 h-12 text-sun-400 mx-auto animate-pulse" /><p class="mt-3 font-display text-[11px] uppercase tracking-[0.3em]">Building the restaurant…</p></div>
                    </div>

                    <div class="absolute top-3 left-3 flex flex-col items-start gap-1.5" x-show="!loading3d">
                        <p class="font-display text-[10px] uppercase tracking-[0.25em] text-white/60">Level</p>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" @click="setFocus(null)" :class="focus === null ? 'bg-sun-400 text-navy-950' : 'bg-black/55 text-white hover:bg-black/75'"
                                    class="px-3 py-1.5 font-display text-[11px] font-semibold uppercase tracking-[0.16em] backdrop-blur transition">Whole building</button>
                            <template x-for="f in floors" :key="f.id">
                                <button type="button" @click="setFocus(f.id)" :class="focus === f.id ? 'bg-sun-400 text-navy-950' : 'bg-black/55 text-white hover:bg-black/75'"
                                        class="px-3 py-1.5 font-display text-[11px] font-semibold uppercase tracking-[0.16em] backdrop-blur transition" x-text="f.name"></button>
                            </template>
                        </div>
                    </div>

                    <div class="absolute top-3 right-3 flex flex-col gap-1.5" x-show="!loading3d">
                        <button type="button" @click="toggleFullscreen()" :class="fullscreen ? 'bg-sun-400 text-navy-950' : 'bg-black/55 text-white hover:bg-black/75'"
                                class="px-3 py-1.5 font-display text-[11px] font-semibold uppercase tracking-[0.16em] backdrop-blur transition" x-text="fullscreen ? 'Exit full screen' : 'Full screen'"></button>
                        <button type="button" @click="toggleRotate()" :class="autoRotate ? 'bg-sun-400 text-navy-950' : 'bg-black/55 text-white hover:bg-black/75'"
                                class="px-3 py-1.5 font-display text-[11px] font-semibold uppercase tracking-[0.16em] backdrop-blur transition" title="Slowly spin the building">Auto-rotate</button>
                        <button type="button" @click="toggleExplode()" x-show="floors.length > 1" :class="exploded ? 'bg-sun-400 text-navy-950' : 'bg-black/55 text-white hover:bg-black/75'"
                                class="px-3 py-1.5 font-display text-[11px] font-semibold uppercase tracking-[0.16em] backdrop-blur transition" title="Lift the floors apart to see each one">Separate floors</button>
                        <button type="button" @click="topView()" class="px-3 py-1.5 bg-black/55 text-white hover:bg-black/75 font-display text-[11px] font-semibold uppercase tracking-[0.16em] backdrop-blur transition">Top view</button>
                        <button type="button" @click="resetView()" class="px-3 py-1.5 bg-black/55 text-white hover:bg-black/75 font-display text-[11px] font-semibold uppercase tracking-[0.16em] backdrop-blur transition">Reset</button>
                    </div>

                    <div class="absolute bottom-3 inset-x-3 flex justify-center pointer-events-none" x-show="!loading3d">
                        <p x-show="hint" x-text="hint" x-cloak class="bg-sun-400 text-navy-950 px-4 py-2 text-sm font-bold shadow-lift"></p>
                        <p x-show="!hint" class="text-[11px] tracking-wider text-white/60 bg-black/40 px-3 py-1.5 backdrop-blur"><span class="hidden sm:inline">Drag to rotate · Scroll to zoom · Right-drag to move · </span>Click a table to select it</p>
                    </div>

                    <div x-show="selectedTable && fullscreen" x-cloak class="absolute bottom-14 left-1/2 -translate-x-1/2 bg-sun-400 text-navy-950 px-4 py-2 text-sm font-bold shadow-lift flex items-center gap-3">
                        <span>Table <span x-text="selectedTable?.label"></span> selected</span>
                        <button type="button" @click="toggleFullscreen(); $nextTick(() => goToDetails())" class="underline">Continue</button>
                    </div>

                    <template x-if="tip">
                        <div class="absolute z-20 pointer-events-none bg-navy-950/95 border border-sun-400/60 text-white px-3 py-2 text-xs shadow-lift"
                             :style="`left:${Math.min(tip.x + 14, 9999)}px;top:${tip.y + 14}px`">
                            <p class="font-display font-bold tracking-wider text-sm"><span x-text="'Table ' + tip.label"></span> <span class="text-sun-300 font-normal" x-text="'· ' + tip.seats + ' seats'"></span> <span x-show="tip.vip" class="ml-1 bg-sun-400 text-navy-950 text-[10px] font-bold px-1.5 py-0.5">★ VIP</span></p>
                            <p class="mt-0.5" :class="{ 'text-emerald-300': tip.state === 'available', 'text-rose-300': tip.state === 'booked', 'text-white/60': tip.state === 'small' || tip.state === 'idle' }"
                               x-text="({ available: 'Available · click to select', booked: 'Booked at this time', small: 'Too small for your party', idle: 'Pick a time to check' })[tip.state]"></p>
                        </div>
                    </template>
                </div>
                <p class="mt-2 text-xs text-navy-400">Look straight through the glass: every table, chair and level is live. Colours update as other guests book.</p>
            </div>

            <div x-show="view === '2d'" x-cloak>
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <template x-for="f in floors" :key="f.id">
                        <button type="button" @click="floorId = f.id" class="px-3.5 py-1.5 text-xs font-display font-semibold uppercase tracking-[0.16em]"
                                :class="floorId === f.id ? 'bg-navy-950 text-sun-300' : 'bg-cream hover:bg-cream-dark'" x-text="f.name"></button>
                    </template>
                </div>
                <template x-if="floor">
                    <div class="relative w-full max-w-5xl mx-auto bg-cream-dark border border-cream-line overflow-hidden select-none"
                         :style="`aspect-ratio: ${floor.width} / ${floor.height}`" role="group" aria-label="Restaurant floor plan">
                        <x-floor-backdrop />
                        <template x-for="t in floor.tables" :key="t.id">
                            <button type="button" @click="pick(t)" :disabled="!['available', 'idle'].includes(stateOf(t)) && selected !== t.id"
                                    :aria-label="`Table ${t.label}, seats ${t.seats}, ${stateOf(t)}`"
                                    class="absolute border-2 flex flex-col items-center justify-center leading-tight text-navy-900 transition-colors"
                                    :class="[
                                        t.shape === 'round' ? 'rounded-full' : '', t.is_vip ? 'ring-2 ring-sun-500 ring-offset-1' : '',
                                        selected === t.id ? 'bg-green-600 border-green-800 text-white ring-2 ring-green-300 ring-offset-1'
                                          : ({ available: 'bg-emerald-100 border-emerald-500 hover:bg-emerald-200 cursor-pointer', booked: 'bg-red-400 border-red-700 cursor-not-allowed', small: 'bg-stone-200 border-stone-400 cursor-not-allowed', idle: 'bg-white border-stone-400 hover:bg-emerald-100 cursor-pointer' })[stateOf(t)]
                                    ]"
                                    :style="`left:${t.x / floor.width * 100}%;top:${t.y / floor.height * 100}%;width:${t.w / floor.width * 100}%;height:${t.h / floor.height * 100}%`">
                                <span class="font-semibold text-xs sm:text-sm" x-text="(t.is_vip ? '★ ' : '') + t.label"></span>
                                <span class="text-[10px] sm:text-xs" :class="selected === t.id ? 'text-white/90' : 'text-navy-500'" x-text="t.seats + ' seats'"></span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>

            <p x-show="!floors.length" class="text-navy-500 text-sm">The restaurant layout isn’t available yet.</p>
        </div>

        <form x-ref="details" @submit.prevent="submit()" class="space-y-4 scroll-mt-20 lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto">

            <section class="bg-white border border-cream-line shadow-card p-4" aria-labelledby="booker-h">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h2 id="booker-h" class="font-display text-xs font-bold uppercase tracking-[0.2em]">Booking for</h2>
                    <span x-show="me" x-cloak class="text-[10px] uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5">From your account</span>
                    <span x-show="!me && remembered" x-cloak class="text-[10px] uppercase tracking-wider text-navy-500 bg-cream border border-cream-line px-1.5 py-0.5">Saved on this device</span>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium" for="rname">Name</label>
                        <input id="rname" x-model="name" required maxlength="100" autocomplete="name" class="mt-1 w-full">
                    </div>
                    <div>
                        <label class="text-sm font-medium" for="rphone">Phone number</label>
                        <input id="rphone" x-model="phone" required inputmode="tel" maxlength="20" autocomplete="tel" class="mt-1 w-full">
                    </div>
                    <div>
                        <label class="text-sm font-medium" for="remail">Email <span class="text-stone-400 font-normal">(we send your receipt here)</span></label>
                        <input id="remail" type="email" x-model="email" required maxlength="150" autocomplete="email" placeholder="you@example.com" class="mt-1 w-full">
                    </div>
                </div>
            </section>

            <section class="bg-white border border-cream-line shadow-card p-4" aria-labelledby="resv-h">
                <h2 id="resv-h" class="font-display text-xs font-bold uppercase tracking-[0.2em] mb-3">Reservation details</h2>

                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="text-sm font-medium" for="date">Date</label>
                        <input id="date" type="date" x-model="date" :min="config.min" :max="config.max" class="mt-1 w-full">
                    </div>
                    <div>
                        <label class="text-sm font-medium" for="time">Time</label>
                        <select id="time" x-model="time" class="mt-1 w-full">
                            <option value="">Select</option>
                            <template x-for="s in slots" :key="s.time"><option :value="s.time" :disabled="s.past" x-text="s.time"></option></template>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium" for="party">Party size</label>
                        <select id="party" x-model.number="party" class="mt-1 w-full">
                            <template x-for="n in config.maxParty" :key="n"><option :value="n" x-text="n + (n === 1 ? ' guest' : ' guests')"></option></template>
                        </select>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <template x-if="selectedTable">
                        <div class="bg-sun-50 border border-sun-200 px-4 py-3 text-sm">
                            <p class="font-bold">Table <span x-text="selectedTable.label"></span> · <span x-text="selectedTable.seats"></span> seats</p>
                            <p class="text-stone-600"><span x-text="date"></span>, <span x-text="time ? time + '–' + endTime : 'choose a time'"></span> · <span x-text="party"></span> guest(s)</p>
                        </div>
                    </template>
                    <template x-if="selectedTable && selectedTable.is_vip">
                        <div class="border border-sun-400 bg-navy-950 text-white p-4 text-sm">
                            <p class="font-display text-[11px] font-bold uppercase tracking-[0.22em] text-sun-300">★ <span x-text="config.vip.title"></span></p>
                            <p class="mt-1 text-white/80" x-text="config.vip.blurb"></p>
                            <ul class="mt-2 space-y-1 text-white/70">
                                <template x-for="p in config.vip.perks" :key="p"><li class="flex gap-2"><span class="text-sun-400">✓</span><span x-text="p"></span></li></template>
                            </ul>
                        </div>
                    </template>
                    <p x-show="!selectedTable" class="text-sm text-stone-500 border border-dashed border-cream-line px-4 py-3">Click a table on the map and choose your time. The table turns green when selected.</p>

                    <div>
                        <label class="text-sm font-medium" for="rnotes">Notes <span class="text-stone-400 font-normal">(optional)</span></label>
                        <textarea id="rnotes" x-model="notes" rows="2" maxlength="300" class="mt-1 w-full" placeholder="Occasion, allergies, seating requests…"></textarea>
                    </div>

                    <fieldset x-show="detailsReady" x-cloak x-transition class="border-t border-cream-line pt-4">
                        <legend class="text-sm font-bold px-1">Payment method</legend>
                        <div class="mt-2"><x-payment-fields model="payMethod" /></div>
                        <dl class="mt-3 text-sm space-y-1 border-t border-cream-line pt-3">
                            <div class="flex justify-between"><dt class="text-stone-600">Reservation deposit (<span x-text="party"></span> × <span x-text="money(config.fee)"></span>)</dt><dd x-text="money(price.sub)"></dd></div>
                            <div class="flex justify-between"><dt class="text-stone-600" x-text="config.taxLabel"></dt><dd x-text="money(price.tax)"></dd></div>
                            <div class="flex justify-between font-bold"><dt>Total</dt><dd x-text="money(price.total)"></dd></div>
                        </dl>
                        <p class="mt-1 text-xs text-navy-500" x-text="payMethod === 'cash' ? 'Pay this in cash when you arrive.' : 'Demo only: your payment is approved instantly and nothing is charged.'"></p>
                    </fieldset>
                    <p x-show="!detailsReady && selectedTable" x-cloak class="text-xs text-stone-500">Fill in your name, phone and email above to choose how to pay.</p>

                    <p x-show="error" x-text="error" class="text-sm text-red-600"></p>
                    <button :disabled="!detailsReady || busy" class="btn-primary w-full" x-text="busy ? 'Booking…' : (payMethod === 'cash' ? 'Confirm reservation' : 'Pay ' + money(price.total) + ' & confirm')"></button>
                </div>
            </section>
        </form>
    </div>

    <div x-show="selectedTable" x-cloak x-transition
         class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-navy-950/95 backdrop-blur border-t border-sun-400/60 text-white">
        <div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-3 flex flex-wrap items-center gap-x-6 gap-y-2">
            <p class="text-sm"><span class="font-display text-[11px] font-bold uppercase tracking-[0.2em] text-sun-300">Selected</span>
                <strong class="ml-2" x-text="selectedTable ? 'Table ' + selectedTable.label + (selectedTable.is_vip ? ' ★ VIP' : '') : ''"></strong>
                <span class="text-white/70" x-text="selectedTable ? ' · ' + selectedTable.seats + ' seats · ' + date + ' ' + time + '–' + endTime : ''"></span></p>
            <button type="button" @click="goToDetails()" class="ml-auto link-gold">Continue to your details ↓</button>
        </div>
    </div>
</div>
</x-kusina-layout>
