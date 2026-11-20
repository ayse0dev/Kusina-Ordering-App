<x-kusina-layout title="Menu">
<section class="hero-ambient text-center text-white py-14">
    <p class="script text-4xl !text-sun-300">Our menu</p>
    <h1 class="mt-1 text-3xl sm:text-4xl font-bold">Lutong Pinoy, made to order</h1>
    <p class="mt-3 text-white/70 max-w-xl mx-auto px-4">Choose a dish, make it yours, and we’ll cook it fresh. Prices shown in CAD; GST is added at checkout.</p>
</section>
<div id="menu" class="max-w-6xl mx-auto px-4 py-8 scroll-mt-20" x-data="menuPage(@js($categories))">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
        <div class="flex gap-6 overflow-x-auto pb-1 -mx-1 px-1">
            <template x-for="c in categories" :key="c.id">
                <button @click="active = c.id; q = ''"
                        :class="(active === c.id && !q) ? 'text-navy-950 border-sun-500' : 'text-navy-400 border-transparent hover:text-navy-900'"
                        class="px-1 py-2 border-b-2 font-display text-xs font-semibold uppercase tracking-[0.2em] whitespace-nowrap transition" x-text="c.name"></button>
            </template>
        </div>
        <input x-model.debounce.200ms="q" type="search" placeholder="Search dishes…" aria-label="Search dishes"
               class="sm:ml-auto w-full sm:w-64 text-sm focus:border-navy-500 focus:ring-navy-500">
    </div>

    <template x-if="categories.length === 0"><p class="text-stone-500">The menu is being prepared. Please check back soon.</p></template>
    <template x-if="categories.length && visible.length === 0"><p class="text-stone-500">No dishes match your search.</p></template>

    <template x-for="c in visible" :key="c.id">
        <section class="mb-8">
            <h2 class="mb-6"><span class="section-title text-2xl" x-text="c.name"></span><span class="section-rule"></span></h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="i in c.items" :key="i.id">
                    <button type="button" @click="openItem(i)" :disabled="!i.available"
                            class="group text-left bg-white rounded-2xl border border-cream-line shadow-card overflow-hidden hover:shadow-lift hover:-translate-y-0.5 transition disabled:opacity-60 disabled:hover:translate-y-0 disabled:cursor-not-allowed flex">
                        <div class="w-28 shrink-0 bg-gradient-to-br from-sun-100 to-sun-200 grid place-items-center text-3xl">
                            <template x-if="i.image"><img :src="i.image" :alt="i.name" class="w-full h-full object-cover" loading="lazy"></template>
                            <template x-if="!i.image"><span aria-hidden="true">🍲</span></template>
                        </div>
                        <div class="p-3 flex-1 min-w-0">
                            <p class="font-display font-bold text-navy-900 leading-snug" x-text="i.name"></p>
                            <p class="text-xs text-stone-500 line-clamp-2 mt-1" x-text="i.description"></p>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="font-bold text-brand-700" x-text="(i.groups.length ? 'from ' : '') + money(i.price)"></span>
                                <span x-show="!i.available" class="text-xs rounded bg-stone-200 px-1.5 py-0.5">Sold out</span>
                                <span x-show="i.available" class="text-xs font-semibold text-navy-700 group-hover:text-brand-600">Customize ›</span>
                            </div>
                        </div>
                    </button>
                </template>
            </div>
        </section>
    </template>

    {{-- Item builder modal --}}
    <div x-cloak x-show="item" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center" @keydown.escape.window="close()">
        <div class="absolute inset-0 bg-navy-950/50" @click="close()"></div>
        <div class="relative bg-white w-full sm:max-w-lg max-h-[92vh] rounded-t-2xl sm:rounded-2xl flex flex-col shadow-lift" x-show="item">
            <template x-if="item">
                <div class="flex flex-col min-h-0">
                    <div class="px-5 pt-5 pb-3 border-b">
                        <div class="flex justify-between gap-3">
                            <h3 class="font-display text-2xl font-bold text-navy-800" x-text="item.name"></h3>
                            <button @click="close()" class="text-stone-400 hover:text-stone-700" aria-label="Close">✕</button>
                        </div>
                        <p class="text-sm text-stone-500 mt-1" x-text="item.description"></p>
                    </div>

                    <div class="px-5 py-4 overflow-y-auto space-y-5">
                        <template x-for="g in item.groups" :key="g.id">
                            <fieldset>
                                <legend class="flex items-center gap-2 font-medium mb-2">
                                    <span x-text="g.name"></span>
                                    <span class="text-xs font-normal rounded px-1.5 py-0.5" :class="g.required ? 'bg-brand-100 text-brand-700' : 'bg-stone-100 text-stone-500'"
                                          x-text="g.required ? 'Required' : (g.type === 'multiple' && g.max > 0 ? 'Up to ' + g.max : 'Optional')"></span>
                                </legend>
                                <div class="space-y-1.5">
                                    <template x-for="o in g.options" :key="o.id">
                                        <label class="flex items-center gap-3 rounded-lg border px-3 py-2 cursor-pointer hover:bg-stone-50"
                                               :class="has(g, o) ? 'border-brand-500 bg-brand-50' : 'border-cream-line'">
                                            <input :type="g.type === 'single' ? 'radio' : 'checkbox'" :name="'g' + g.id" :checked="has(g, o)" @change="toggle(g, o)"
                                                   class="text-brand-600 focus:ring-brand-500" :class="g.type === 'single' ? '' : 'rounded'">
                                            <span class="flex-1 text-sm" x-text="o.name"></span>
                                            <span class="text-xs text-stone-500" x-show="o.delta !== 0" x-text="(o.delta > 0 ? '+' : '−') + money(Math.abs(o.delta))"></span>
                                        </label>
                                    </template>
                                </div>
                            </fieldset>
                        </template>

                        <div>
                            <label class="font-medium text-sm" for="notes">Special instructions</label>
                            <textarea id="notes" x-model="notes" maxlength="200" rows="2" placeholder="e.g. less salty, no onions"
                                      class="mt-1 w-full rounded-lg border-stone-300 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                        </div>
                    </div>

                    <div class="px-5 py-4 border-t space-y-2">
                        <p x-show="error" x-text="error" class="text-sm text-red-600"></p>
                        <div class="flex items-center gap-3">
                            <div class="inline-flex items-center border rounded-lg">
                                <button class="px-3 py-2 hover:bg-stone-100" @click="qty = Math.max(1, qty - 1)" aria-label="Decrease">−</button>
                                <span class="w-8 text-center text-sm" x-text="qty"></span>
                                <button class="px-3 py-2 hover:bg-stone-100" @click="qty = Math.min(50, qty + 1)" aria-label="Increase">+</button>
                            </div>
                            <button @click="addToCart()" :disabled="busy"
                                    class="flex-1 rounded-lg bg-brand-600 text-white py-2.5 font-medium hover:bg-brand-700 disabled:opacity-60 flex justify-between px-4">
                                <span>Add to cart</span><span x-text="money(total)"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
</x-kusina-layout>