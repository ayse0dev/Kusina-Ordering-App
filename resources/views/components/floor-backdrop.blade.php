<div class="absolute inset-0 pointer-events-none" aria-hidden="true"
     x-data="{
        cls: {
            kitchen:   'bg-stone-300/80 border-stone-600',
            bathroom:  'bg-sky-100 border-sky-500',
            office:    'bg-rose-100 border-rose-500 border-dashed',
            vip:       'bg-[#f1d4da] border-amber-500',
            patio:     'bg-emerald-50 border-emerald-600 border-dashed',
            bar:       'bg-amber-100 border-amber-700',
            stairs:    'bg-stone-200 border-stone-600',
            reception: 'bg-orange-100 border-orange-500',
            waiting:   'bg-teal-50 border-teal-500',
            door:      'bg-amber-400 border-amber-600',
        },
        pts(f) { return (f.outline || []).map((p) => (p[0] / f.width * 100).toFixed(3) + ',' + (p[1] / f.height * 100).toFixed(3)).join(' '); },
     }">
    <template x-if="floor">
        <div class="absolute inset-0">
            <svg class="absolute inset-0 w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                <polygon x-show="floor.outline" :points="pts(floor)" fill="#ffffff" stroke="#1c1917" stroke-width="3" vector-effect="non-scaling-stroke" stroke-linejoin="round"></polygon>
            </svg>

            <template x-for="(z, i) in (floor.zones || [])" :key="i">
                <div class="absolute border-2 flex flex-col items-center justify-center text-center overflow-hidden"
                     :class="cls[z.type] || 'bg-stone-100 border-stone-400'"
                     :style="`left:${z.x / floor.width * 100}%;top:${z.y / floor.height * 100}%;width:${z.w / floor.width * 100}%;height:${z.h / floor.height * 100}%;` + (z.type === 'stairs' ? 'background-image:repeating-linear-gradient(0deg,rgba(0,0,0,.18) 0 2px,transparent 2px 9px);' : '')">
                    <template x-if="z.type !== 'door'">
                        <span class="font-display font-bold uppercase tracking-[0.12em] text-[8px] sm:text-[11px] leading-tight text-stone-800 px-1" x-text="z.type === 'vip' ? '★ ' + z.label : z.label"></span>
                    </template>
                    <template x-if="z.note && z.type !== 'door'">
                        <span class="text-[7px] sm:text-[9px] uppercase tracking-wider text-stone-600 leading-tight px-1" x-text="z.note"></span>
                    </template>
                </div>
            </template>
        </div>
    </template>
</div>
