// Alpine components for the Kusina ordering app.

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export const money = (n) =>
    Number(n || 0).toLocaleString('en-CA', { style: 'currency', currency: 'CAD' });

window.money = money;

/** JSON fetch helper that throws the parsed body on non-2xx. */
async function api(method, url, body) {
    const res = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    let data = {};
    try { data = await res.json(); } catch (_) { /* empty body */ }
    if (!res.ok) throw data;
    return data;
}

const firstError = (e) =>
    (e && e.errors ? Object.values(e.errors).flat()[0] : null) || (e && e.message) || 'Something went wrong.';

export default function registerKusina(Alpine) {
    // ---------- Cart (shared store) ----------
    Alpine.store('cart', {
        open: false,
        lines: [],
        subtotal: 0,
        count: 0,
        error: '',

        apply(d) { this.lines = d.lines; this.subtotal = d.subtotal; this.count = d.count; },
        async load() { try { this.apply(await api('GET', '/cart')); } catch (_) {} },
        async add(payload) { this.apply(await api('POST', '/cart', payload)); },
        async setQty(key, qty) {
            try { this.apply(await api('PATCH', '/cart/' + key, { qty })); } catch (e) { this.error = firstError(e); }
        },
        async remove(key) { try { this.apply(await api('DELETE', '/cart/' + key)); } catch (_) {} },
    });

    // ---------- Menu + dynamic item builder ----------
    Alpine.data('menuPage', (categories) => ({
        categories,
        active: categories[0]?.id ?? null,
        q: '',
        item: null,
        picks: {},
        qty: 1,
        notes: '',
        error: '',
        busy: false,

        get visible() {
            const q = this.q.trim().toLowerCase();
            return this.categories
                .map((c) => ({
                    ...c,
                    items: c.items.filter((i) => !q || (i.name + ' ' + (i.description || '')).toLowerCase().includes(q)),
                }))
                .filter((c) => c.items.length && (q || c.id === this.active));
        },

        openItem(i) {
            if (!i.available) return;
            this.item = i;
            this.qty = 1;
            this.notes = '';
            this.error = '';
            const picks = {};
            i.groups.forEach((g) => {
                picks[g.id] = g.required && g.type === 'single' && g.options.length ? [g.options[0].id] : [];
            });
            this.picks = picks;
        },
        close() { this.item = null; },

        has(g, o) { return (this.picks[g.id] || []).includes(o.id); },
        toggle(g, o) {
            let cur = [...(this.picks[g.id] || [])];
            if (g.type === 'single') {
                cur = [o.id];
            } else if (cur.includes(o.id)) {
                cur = cur.filter((x) => x !== o.id);
            } else {
                if (g.max > 0 && cur.length >= g.max) return;
                cur.push(o.id);
            }
            this.picks = { ...this.picks, [g.id]: cur };
        },

        get unit() {
            if (!this.item) return 0;
            let t = this.item.price;
            this.item.groups.forEach((g) =>
                g.options.forEach((o) => { if (this.has(g, o)) t += o.delta; }));
            return Math.max(0, t);
        },
        get total() { return this.unit * this.qty; },
        get missing() {
            return this.item?.groups.find((g) => g.required && !(this.picks[g.id] || []).length);
        },

        async addToCart() {
            if (this.missing) { this.error = `Please choose an option for “${this.missing.name}”.`; return; }
            this.busy = true;
            this.error = '';
            try {
                await Alpine.store('cart').add({
                    item_id: this.item.id,
                    option_ids: Object.values(this.picks).flat(),
                    qty: this.qty,
                    notes: this.notes || null,
                });
                this.close();
                Alpine.store('cart').open = true;
            } catch (e) {
                this.error = firstError(e);
            } finally {
                this.busy = false;
            }
        },
    }));

    // ---------- Order tracking (customer) ----------
    Alpine.data('trackPage', ({ code, status, payment, steps, delivery }) => ({
        code, status, payment, steps, delivery,
        get idx() { return this.steps.findIndex((s) => s.key === this.status); },
        init() {
            window.Echo?.channel('orders.' + this.code).listen('.order.updated', (e) => {
                this.status = e.status;
                this.payment = e.payment_status;
                this.poll(); // courier details aren't in the broadcast; fetch them
            });
            // Fallback if the socket is unavailable.
            setInterval(() => this.poll(), 20000);
        },
        async poll() {
            try {
                const d = await api('GET', `/track/${this.code}/status`);
                this.status = d.status;
                this.payment = d.payment_status;
                this.delivery = d.delivery;
            } catch (_) {}
        },
    }));

    // ---------- Reservations (customer booking: 3D building + 2D plan) ----------
    Alpine.data('reservationPage', ({ floors, config }) => {
        // The three.js scene lives outside Alpine's reactive proxy (proxying GPU objects is slow and breaks them).
        let scene = null;
        let hintTimer = null;

        return {
            floors, config,
            floorId: floors[0]?.id ?? null,
            date: config.min,
            time: '',
            party: 2,
            slots: [],
            states: {},
            selected: null,
            name: '', phone: '', notes: '',
            error: '', busy: false,

            // 3D view state
            view: '3d',          // '3d' | '2d'
            focus: null,         // null = whole building, otherwise a floor id
            loading3d: true,
            fail3d: false,
            autoRotate: true,
            tip: null,
            hint: '',
            exploded: false,
            fullscreen: false,

            get floor() { return this.floors.find((f) => f.id === this.floorId); },
            get selectedTable() { return this.tableById(this.selected); },
            get vipFloor() { return this.floors.find((f) => f.tables.some((t) => t.is_vip)) ?? null; },
            get endTime() {
                if (!this.time) return '';
                const [h, m] = this.time.split(':').map(Number);
                const t = h * 60 + m + this.config.minutes;
                return String(Math.floor(t / 60)).padStart(2, '0') + ':' + String(t % 60).padStart(2, '0');
            },

            tableById(id) {
                for (const f of this.floors) {
                    const t = f.tables.find((x) => x.id === id);
                    if (t) return t;
                }
                return null;
            },
            floorOf(id) { return this.floors.find((f) => f.tables.some((t) => t.id === id)); },

            init() {
                // Shareable links: /reservations?date=2026-12-01&time=19:00&party=4
                const q = new URLSearchParams(window.location.search);
                const qDate = q.get('date') || '';
                if (/^\d{4}-\d{2}-\d{2}$/.test(qDate) && qDate >= config.min && qDate <= config.max) this.date = qDate;
                const party = parseInt(q.get('party'), 10);
                if (party >= 1 && party <= config.maxParty) this.party = party;
                const pendingTime = /^\d{2}:\d{2}$/.test(q.get('time') || '') ? q.get('time') : '';

                this.refresh().then(() => {
                    if (pendingTime && this.slots.some((s) => s.time === pendingTime && !s.past)) this.time = pendingTime;
                });
                this.$watch('date', () => { this.time = ''; this.selected = null; this.refresh(); });
                this.$watch('time', () => this.refresh());
                this.$watch('party', () => this.refresh());
                this.$watch('states', () => this.sync3d());
                this.$watch('selected', (id) => scene?.setSelected(id));
                window.Echo?.channel('reservations').listen('.reservations.changed', () => this.refresh());

                this._fs = () => { this.fullscreen = !!document.fullscreenElement; };
                document.addEventListener('fullscreenchange', this._fs);

                this.boot3d();
            },

            destroy() {
                document.removeEventListener('fullscreenchange', this._fs);
                scene?.destroy();
                scene = null;
            },

            async boot3d() {
                if (!this.floors.length) { this.loading3d = false; this.view = '2d'; return; }
                try {
                    const { createScene } = await import('./reservation3d');
                    scene = createScene(this.$refs.stage, JSON.parse(JSON.stringify(this.floors)), {
                        onPick: (id) => this.pickId(id),
                        onHover: (h) => this.hover(h),
                    });
                    this.autoRotate = scene.autoRotate;
                    this.sync3d();
                    scene.setSelected(this.selected);
                    // Deep link to one level: /reservations?level=<floor id>
                    const lv = parseInt(new URLSearchParams(window.location.search).get('level'), 10);
                    if (this.floors.some((f) => f.id === lv)) this.setFocus(lv);
                } catch (e) {
                    console.warn('3D view unavailable, using the 2D plan.', e);
                    this.fail3d = true;
                    this.view = '2d';
                } finally {
                    this.loading3d = false;
                }
            },

            sync3d() { scene?.setStates(this.states, !!this.time); },

            async refresh() {
                const p = new URLSearchParams({ date: this.date });
                if (this.time) { p.set('time', this.time); p.set('party', this.party); }
                try {
                    const d = await api('GET', '/reservations/availability?' + p);
                    this.slots = d.slots;
                    this.states = d.tables;
                    if (this.selected && this.states[this.selected] !== 'available') this.selected = null;
                } catch (_) {}
            },

            stateOf(t) { return this.time ? (this.states[t.id] || 'booked') : 'idle'; },
            pick(t) { if (this.stateOf(t) === 'available') this.selected = t.id; },

            // ----- 3D interactions -----
            pickId(id) {
                const t = this.tableById(id);
                if (!t) return;
                const st = this.stateOf(t);
                if (st === 'available') {
                    this.selected = id;
                    this.floorId = this.floorOf(id)?.id ?? this.floorId;
                    this.hint = '';
                } else {
                    this.flash(st === 'idle' ? 'Choose a time first to see which tables are free.'
                        : st === 'booked' ? `Table ${t.label} is already booked at that time.`
                        : `Table ${t.label} seats ${t.seats}, too small for ${this.party} guests.`);
                }
            },
            hover(h) {
                if (!h) { this.tip = null; return; }
                const t = this.tableById(h.id);
                this.tip = t ? { x: h.x, y: h.y, label: t.label, seats: t.seats, vip: !!t.is_vip, state: this.stateOf(t) } : null;
            },
            flash(msg) {
                this.hint = msg;
                clearTimeout(hintTimer);
                hintTimer = setTimeout(() => { this.hint = ''; }, 3500);
            },
            setFocus(id) {
                this.focus = id;
                if (id) this.floorId = id;
                scene?.focusFloor(id);
            },
            resetView() { this.autoRotate = false; scene?.resetView(); },
            topView() { this.autoRotate = false; scene?.setAutoRotate(false); scene?.topView(); },
            toggleFullscreen() {
                if (document.fullscreenElement) { document.exitFullscreen?.(); return; }
                this.$refs.stageWrap?.requestFullscreen?.().catch(() => this.flash('Full screen is not available in this browser.'));
            },
            goToDetails() { this.$refs.details?.scrollIntoView({ behavior: 'smooth', block: 'start' }); },
            toggleExplode() { this.exploded = !this.exploded; scene?.setExploded(this.exploded); },
            showVip() {
                if (!this.vipFloor) return;
                this.view = '3d';
                this.setFocus(this.vipFloor.id);
            },
            toggleRotate() { this.autoRotate = !this.autoRotate; scene?.setAutoRotate(this.autoRotate); },

            async submit() {
                this.error = '';
                this.busy = true;
                try {
                    const d = await api('POST', '/reservations', {
                        table_id: this.selected, date: this.date, time: this.time, party_size: this.party,
                        customer_name: this.name, customer_phone: this.phone, notes: this.notes || null,
                    });
                    window.location = d.redirect;
                } catch (e) {
                    this.error = firstError(e);
                    this.refresh();
                } finally {
                    this.busy = false;
                }
            },
        };
    });

    // ---------- Staff: live order board ----------
    Alpine.data('orderBoard', () => ({
        orders: [],
        sound: false,
        loaded: false,
        columns: [
            { key: 'pending', title: 'New' },
            { key: 'preparing', title: 'Preparing' },
            { key: 'ready', title: 'Ready' },
            { key: 'out_for_delivery', title: 'Out for delivery' },
        ],

        init() {
            this.load();
            setInterval(() => this.load(), 15000); // fallback if the socket drops
            const ch = window.Echo?.private('staff');
            ch?.listen('.order.placed', () => { this.beep(); this.load(); });
            ch?.listen('.order.updated', () => this.load());
        },

        async load() {
            try { this.orders = (await api('GET', '/admin/orders/feed')).orders; this.loaded = true; } catch (_) {}
        },
        inColumn(key) { return this.orders.filter((o) => o.status === key); },

        async act(o, kind) {
            if (kind === 'cancel' && !confirm(`Cancel order ${o.code}?`)) return;
            try { await api('POST', `/admin/orders/${o.id}/${kind}`); } catch (e) { alert(firstError(e)); }
            this.load();
        },

        // Own-driver deliveries: record who is delivering.
        async assignDriver(o) {
            const name = (window.prompt('Driver name') || '').trim();
            if (!name) return;
            const phone = (window.prompt('Driver phone (optional)') || '').trim();
            try { await api('POST', `/admin/orders/${o.id}/courier/driver`, { name, phone: phone || null }); } catch (e) { alert(firstError(e)); }
            this.load();
        },

        toggleSound() { this.sound = !this.sound; if (this.sound) this.beep(); },
        beep() {
            if (!this.sound) return;
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                [880, 1175].forEach((f, i) => {
                    const o = ctx.createOscillator(); const g = ctx.createGain();
                    o.frequency.value = f; o.connect(g); g.connect(ctx.destination);
                    g.gain.setValueAtTime(0.15, ctx.currentTime + i * 0.18);
                    o.start(ctx.currentTime + i * 0.18); o.stop(ctx.currentTime + i * 0.18 + 0.15);
                });
            } catch (_) {}
        },
    }));

    // ---------- Staff: reservations auto-refresh ----------
    Alpine.data('reservationBoard', () => ({
        init() { window.Echo?.private('staff'); window.Echo?.channel('reservations').listen('.reservations.changed', () => window.location.reload()); },
    }));

    // ---------- Admin: drag & drop floor editor ----------
    Alpine.data('floorEditor', ({ floors }) => ({
        floors,
        floorId: floors[0]?.id ?? null,
        sel: null,
        drag: null,
        saving: false,
        msg: '',

        get floor() { return this.floors.find((f) => f.id === this.floorId); },
        get selected() { return this.floor?.tables.find((t) => t._k === this.sel) ?? null; },

        init() {
            this.floors.forEach((f) => f.tables.forEach((t) => { t._k = 'k' + t.id; }));
        },

        add() {
            const n = this.floor.tables.length + 1;
            const t = { id: null, _k: 'n' + Date.now(), label: 'T' + n, seats: 2, shape: 'rect', x: 20, y: 20, w: 80, h: 80, is_active: true, is_vip: false };
            this.floor.tables.push(t);
            this.sel = t._k;
        },
        remove() {
            if (!this.selected) return;
            this.floor.tables = this.floor.tables.filter((t) => t._k !== this.sel);
            this.sel = null;
        },

        // Convert a pointer position to floor-plan coordinates.
        point(e) {
            const r = this.$refs.canvas.getBoundingClientRect();
            return {
                x: (e.clientX - r.left) * (this.floor.width / r.width),
                y: (e.clientY - r.top) * (this.floor.height / r.height),
            };
        },
        down(e, t) {
            this.sel = t._k;
            const p = this.point(e);
            this.drag = { t, dx: p.x - t.x, dy: p.y - t.y };
            e.currentTarget.setPointerCapture?.(e.pointerId);
        },
        move(e) {
            if (!this.drag) return;
            const p = this.point(e);
            const { t } = this.drag;
            const snap = (v) => Math.round(v / 10) * 10;
            t.x = Math.max(0, Math.min(this.floor.width - t.w, snap(p.x - this.drag.dx)));
            t.y = Math.max(0, Math.min(this.floor.height - t.h, snap(p.y - this.drag.dy)));
        },
        up() { this.drag = null; },

        async save() {
            this.saving = true;
            this.msg = '';
            try {
                const payload = this.floor.tables.map(({ _k, ...t }) => t);
                const d = await api('PUT', `/admin/floor/${this.floorId}/tables`, { tables: payload });
                d.tables.forEach((t) => { t._k = 'k' + t.id; });
                this.floor.tables = d.tables;
                this.sel = null;
                this.msg = 'Saved.';
            } catch (e) {
                this.msg = firstError(e);
            } finally {
                this.saving = false;
            }
        },
    }));

    // ---------- Admin: option groups editor on the item form ----------
    Alpine.data('itemForm', ({ groups }) => ({
        groups,
        addGroup() { this.groups.push({ name: '', type: 'single', is_required: false, max_select: 0, options: [{ name: '', price_delta: 0, is_available: true }] }); },
        removeGroup(i) { this.groups.splice(i, 1); },
        addOption(g) { g.options.push({ name: '', price_delta: 0, is_available: true }); },
        removeOption(g, i) { g.options.splice(i, 1); },
    }));

    // ---------- Checkout: live totals with voucher / discount codes ----------
    Alpine.data('checkoutPage', ({ type, pay, quote, address }) => ({
        type, pay, quote, address,
        codeInput: '',
        codes: [],
        codeMsg: '',
        busy: false,

        init() {
            this.$watch('type', () => this.refresh());
            // Live courier price once the customer has typed an address (the input is debounced in the view).
            this.$watch('address', () => { if (this.type === 'delivery') this.refresh(); });
            if (this.type === 'delivery' && this.address) this.refresh();
        },
        body() {
            return { order_type: this.type, codes: this.codes, delivery_address: this.type === 'delivery' ? this.address : null };
        },

        async refresh() {
            try {
                this.quote = await api('POST', '/checkout/quote', this.body());
                // Drop any code the server rejected, and tell the customer why.
                const bad = Object.keys(this.quote.errors || {});
                if (bad.length) {
                    this.codeMsg = Object.values(this.quote.errors)[0];
                    this.codes = this.codes.filter((c) => !bad.includes(c));
                    this.quote = await api('POST', '/checkout/quote', this.body());
                }
            } catch (_) {}
        },
        async addCode() {
            const c = this.codeInput.trim().toUpperCase().replace(/[^A-Z0-9-]/g, '');
            if (!c) return;
            this.codeMsg = '';
            if (this.codes.includes(c)) { this.codeMsg = 'Code already added.'; return; }
            this.busy = true;
            this.codes.push(c);
            await this.refresh();
            if (this.codes.includes(c)) this.codeInput = '';
            this.busy = false;
        },
        async removeCode(c) { this.codes = this.codes.filter((x) => x !== c); this.codeMsg = ''; await this.refresh(); },
    }));

    // ---------- Vouchers ----------
    Alpine.data('giftForm', ({ presets, min, max, amount }) => ({ presets, min, max, amount }));

    Alpine.data('voucherCheck', () => ({
        code: '', msg: '', ok: false, busy: false,
        async check() {
            this.busy = true; this.msg = '';
            try {
                const d = await api('POST', '/vouchers/check', { code: this.code });
                this.ok = d.valid; this.msg = d.message;
            } catch (e) { this.ok = false; this.msg = firstError(e); }
            this.busy = false;
        },
    }));

    Alpine.data('voucherPurchase', ({ token, status }) => ({
        status,
        init() {
            if (this.status !== 'pending') return;
            const t = setInterval(async () => {
                try {
                    const d = await api('GET', `/vouchers/purchase/${token}/status`);
                    if (d.status !== 'pending') { clearInterval(t); window.location.reload(); }
                } catch (_) {}
            }, 3000);
        },
    }));

    // ---------- Home: feature slider ----------
    Alpine.data('featureSlider', ({ count }) => ({
        i: 0,
        count,
        paused: false,
        startX: null,

        init() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return; // no auto-advance
            setInterval(() => { if (!this.paused) this.next(); }, 7000);
        },
        go(n) { this.i = (n + this.count) % this.count; },
        next() { this.go(this.i + 1); },
        prev() { this.go(this.i - 1); },

        // Swipe on touch screens
        touchStart(e) { this.startX = e.touches[0].clientX; },
        touchEnd(e) {
            if (this.startX === null) return;
            const dx = e.changedTouches[0].clientX - this.startX;
            if (Math.abs(dx) > 50) (dx < 0 ? this.next() : this.prev());
            this.startX = null;
        },
    }));}
