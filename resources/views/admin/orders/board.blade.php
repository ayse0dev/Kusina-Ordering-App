<x-admin-layout title="Orders">
<div x-data="orderBoard()">
    <div class="flex items-center gap-3 mb-4">
        <h1 class="text-xl font-semibold">Live orders</h1>
        <span class="text-xs text-emerald-700 bg-emerald-100 rounded-full px-2 py-0.5">● live</span>
        <button @click="toggleSound()" class="ml-auto text-sm rounded-lg border bg-white px-3 py-1.5 hover:bg-stone-50"
                x-text="sound ? '🔔 Sound on' : '🔕 Enable sound'"></button>
    </div>

    <p x-show="loaded && orders.length === 0" class="text-stone-500 text-sm mb-4">No active orders. New ones appear here instantly.</p>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4 items-start">
        <template x-for="col in columns" :key="col.key">
            <section class="bg-stone-200/60 rounded-xl p-3">
                <h2 class="font-semibold text-sm mb-3 flex justify-between">
                    <span x-text="col.title"></span>
                    <span class="rounded-full bg-white px-2 text-xs grid place-items-center" x-text="inColumn(col.key).length"></span>
                </h2>
                <div class="space-y-3">
                    <template x-for="o in inColumn(col.key)" :key="o.id">
                        <article class="bg-white rounded-lg shadow-sm border border-cream-line p-3 text-sm">
                            <div class="flex justify-between items-start gap-2">
                                <div>
                                    <p class="font-semibold" x-text="o.code"></p>
                                    <p class="text-xs text-stone-500" x-text="o.placed"></p>
                                </div>
                                <span class="text-xs rounded-full px-2 py-0.5 bg-brand-100 text-brand-700" x-text="o.type"></span>
                            </div>

                            <p class="mt-2"><span x-text="o.customer"></span> · <a class="text-brand-700" :href="'tel:' + o.phone" x-text="o.phone"></a></p>
                            <p x-show="o.table" class="text-xs text-stone-600">Table <span x-text="o.table"></span></p>
                            <p x-show="o.address" class="text-xs text-stone-600" x-text="o.address"></p>

                            {{-- Courier (delivery orders) --}}
                            <template x-if="o.delivery">
                                <div class="mt-2 border border-cream-line bg-cream p-2 text-xs space-y-1">
                                    <p class="flex justify-between gap-2"><span class="font-bold uppercase tracking-wider" x-text="'Courier: ' + o.delivery.provider"></span><span x-text="o.delivery.label"></span></p>
                                    <p x-show="o.delivery.courier" x-text="'Driver: ' + o.delivery.courier + (o.delivery.phone ? ' · ' + o.delivery.phone : '')"></p>
                                    <p x-show="o.delivery.error" class="text-brand-700" x-text="o.delivery.error"></p>
                                    <div class="flex flex-wrap gap-x-3 gap-y-1 pt-0.5">
                                        <button type="button" x-show="o.delivery.can_request" @click="act(o, 'courier/dispatch')" class="underline font-bold" x-text="o.delivery.error ? 'Retry courier' : 'Request courier now'"></button>
                                        <button type="button" x-show="o.delivery.provider === 'manual'" @click="assignDriver(o)" class="underline">Assign driver</button>
                                        <a x-show="o.delivery.tracking" :href="o.delivery.tracking" target="_blank" rel="noopener noreferrer" class="underline">Live tracking</a>
                                        <button type="button" x-show="o.delivery.active && o.delivery.provider !== 'manual'" @click="act(o, 'courier/cancel')" class="underline text-brand-700">Cancel courier</button>
                                    </div>
                                </div>
                            </template>

                            <ul class="mt-2 border-t pt-2 space-y-1">
                                <template x-for="(i, idx) in o.items" :key="idx">
                                    <li>
                                        <span class="font-medium" x-text="i.qty + '×'"></span> <span x-text="i.name"></span>
                                        <span x-show="i.options.length" class="block text-xs text-stone-500 ml-5" x-text="i.options.join(' · ')"></span>
                                        <span x-show="i.notes" class="block text-xs text-sun-700 ml-5" x-text="'“' + i.notes + '”'"></span>
                                    </li>
                                </template>
                            </ul>
                            <p x-show="o.notes" class="mt-2 text-xs bg-sun-50 text-sun-800 rounded p-2" x-text="'Note: ' + o.notes"></p>

                            <div class="mt-2 flex items-center justify-between">
                                <span class="font-semibold"><span x-text="money(o.total)"></span> <span x-show="o.gift_applied > 0" class="text-xs font-normal text-emerald-700" x-text="'(voucher −' + money(o.gift_applied) + ')'"></span></span>
                                <span class="text-xs rounded px-1.5 py-0.5" :class="o.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                                      x-text="o.payment_status === 'paid' ? 'Paid' : 'Unpaid · ' + (o.payment_method === 'counter' ? 'at counter' : 'online')"></span>
                            </div>

                            <div class="mt-3 flex gap-2">
                                <button x-show="o.next" @click="act(o, 'advance')" class="flex-1 rounded-lg bg-brand-600 text-white py-1.5 font-medium hover:bg-brand-700"
                                        x-text="'→ ' + o.next_label"></button>
                                <button x-show="o.payment_status !== 'paid'" @click="act(o, 'paid')" class="rounded-lg border px-2 py-1.5 hover:bg-stone-50" title="Mark as paid">$ Paid</button>
                                <button @click="act(o, 'cancel')" class="rounded-lg border border-red-200 text-red-600 px-2 py-1.5 hover:bg-red-50" title="Cancel order">✕</button>
                            </div>
                        </article>
                    </template>
                </div>
            </section>
        </template>
    </div>
</div>
</x-admin-layout>
