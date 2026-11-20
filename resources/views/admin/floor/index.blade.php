<x-admin-layout title="Floor plan">
<div x-data="floorEditor(@js(['floors' => $floors]))">
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <h1 class="text-xl font-semibold">Floor plan editor</h1>
        <div class="flex gap-2">
            <template x-for="f in floors" :key="f.id">
                <button @click="floorId = f.id; sel = null" class="px-3 py-1 rounded-full text-sm"
                        :class="floorId === f.id ? 'bg-brand-600 text-white' : 'bg-white border hover:bg-stone-50'" x-text="f.name"></button>
            </template>
        </div>
        <form method="POST" action="{{ route('admin.floor.store') }}" class="ml-auto flex gap-2">
            @csrf
            <input name="name" required maxlength="60" placeholder="New floor name" class="rounded-lg border-stone-300 text-sm">
            <button class="rounded-lg border bg-white px-3 py-1.5 text-sm hover:bg-stone-50">Add floor</button>
        </form>
    </div>

    <p x-show="!floors.length" class="text-stone-500">Add a floor to begin.</p>

    <div class="grid gap-5 lg:grid-cols-4" x-show="floor">
        <div class="lg:col-span-3 bg-white rounded-2xl border border-cream-line shadow-card p-4">
            <div class="flex items-center gap-3 mb-3">
                <button type="button" @click="add()" class="rounded-lg bg-stone-800 text-white px-3 py-1.5 text-sm">+ Add table</button>
                <p class="text-xs text-stone-500">Drag tables to position them (snaps to a 10 px grid). Click a table to edit it.</p>
            </div>
            <template x-if="floor">
                <div x-ref="canvas" @pointermove="move($event)" @pointerup="up()" @pointerleave="up()"
                     class="relative w-full rounded-lg bg-stone-100 border border-dashed border-stone-300 overflow-hidden select-none touch-none"
                     :style="`aspect-ratio: ${floor.width} / ${floor.height}; background-image: radial-gradient(#d6d3d1 1px, transparent 1px); background-size: ${10 / floor.width * 100}% ${10 / floor.height * 100}%`">
                    <template x-for="t in floor.tables" :key="t._k">
                        <div @pointerdown.prevent="down($event, t)"
                             class="absolute border-2 flex flex-col items-center justify-center leading-tight cursor-move text-stone-800"
                             :class="[t.shape === 'round' ? 'rounded-full' : 'rounded-lg', sel === t._k ? 'bg-sun-200 border-sun-600 z-10' : 'bg-white border-stone-500', t.is_active ? '' : 'opacity-40', t.is_vip ? 'ring-2 ring-sun-500' : '']"
                             :style="`left:${t.x / floor.width * 100}%;top:${t.y / floor.height * 100}%;width:${t.w / floor.width * 100}%;height:${t.h / floor.height * 100}%`">
                            <span class="font-semibold text-xs sm:text-sm" x-text="(t.is_vip ? '★ ' : '') + t.label"></span>
                            <span class="text-[10px] sm:text-xs text-stone-600" x-text="t.seats + ' seats'"></span>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-2xl border border-cream-line shadow-card p-4 space-y-3" x-show="selected">
                <h2 class="font-semibold text-sm">Table details</h2>
                <template x-if="selected">
                    <div class="space-y-3 text-sm">
                        <div><label class="text-xs font-medium">Label</label><input x-model="selected.label" maxlength="20" class="mt-1 w-full rounded-lg border-stone-300 text-sm"></div>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="text-xs font-medium">Seats</label><input type="number" min="1" max="30" x-model.number="selected.seats" class="mt-1 w-full rounded-lg border-stone-300 text-sm"></div>
                            <div><label class="text-xs font-medium">Shape</label>
                                <select x-model="selected.shape" class="mt-1 w-full rounded-lg border-stone-300 text-sm"><option value="rect">Rectangle</option><option value="round">Round</option></select></div>
                            <div><label class="text-xs font-medium">Width</label><input type="number" min="30" max="400" step="10" x-model.number="selected.w" class="mt-1 w-full rounded-lg border-stone-300 text-sm"></div>
                            <div><label class="text-xs font-medium">Height</label><input type="number" min="30" max="400" step="10" x-model.number="selected.h" class="mt-1 w-full rounded-lg border-stone-300 text-sm"></div>
                        </div>
                        <label class="flex items-center gap-2"><input type="checkbox" x-model="selected.is_active" class="rounded text-brand-600"> Bookable</label>
                        <label class="flex items-center gap-2"><input type="checkbox" x-model="selected.is_vip" class="rounded text-brand-600"> <span>★ VIP table <span class="text-xs text-navy-400">(gold rug &amp; badge for guests)</span></span></label>
                        <button type="button" @click="remove()" class="text-red-600 hover:underline">Remove table</button>
                    </div>
                </template>
            </div>

            <div class="bg-white rounded-2xl border border-cream-line shadow-card p-4">
                <button type="button" @click="save()" :disabled="saving" class="w-full rounded-lg bg-brand-600 text-white py-2.5 font-medium hover:bg-brand-700 disabled:opacity-60"
                        x-text="saving ? 'Saving…' : 'Save floor plan'"></button>
                <p class="text-sm mt-2" x-text="msg" :class="msg === 'Saved.' ? 'text-emerald-700' : 'text-red-600'"></p>
                <p class="text-xs text-stone-500 mt-2">Removing a table that has reservations only deactivates it, so booking history is kept.</p>
            </div>
        </div>
    </div>
</div>
</x-admin-layout>
