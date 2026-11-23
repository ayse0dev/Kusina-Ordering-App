
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export const money = (n) =>
    Number(n || 0).toLocaleString('en-CA', { style: 'currency', currency: 'CAD' });

window.money = money;

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
    try { data = await res.json(); } catch (_) { }
    if (!res.ok) throw data;
    return data;
}

const firstError = (e) =>
    (e && e.errors ? Object.values(e.errors).flat()[0] : null) || (e && e.message) || 'Something went wrong.';

export default function registerKusina(Alpine) {
    window.rememberReservation = (entry) => {
        try {
            const list = JSON.parse(localStorage.getItem('kusina.reservations') || '[]').filter((b) => b.code !== entry.code);
            list.unshift(entry);
            localStorage.setItem('kusina.reservations', JSON.stringify(list.slice(0, 8)));
            localStorage.setItem('kusina.reservation', entry.code);
        } catch (_) { }
    };

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

    Alpine.data('trackPage', ({ code, status, payment, steps, delivery, prep, scene3d, dineIn }) => {
        let scene = null;
        let timer = null;
        const pad = (n) => String(n).padStart(2, '0');

        return {
            code, status, payment, steps, delivery, prep,
            clientAt: Date.now(),
            now: Date.now(),
            fail3d: false,
            loading3d: !!scene3d,

            get remaining() {
                if (this.status === 'preparing') return Math.max(0, this.prep.remaining - (this.now - this.clientAt) / 1000);
                if (this.status === 'pending') return this.prep.total;
                return 0;
            },
            get percent() {
                if (this.status === 'pending' || this.status === 'cancelled') return 0;
                if (this.status === 'preparing') return this.prep.total ? Math.min(99, 100 - (this.remaining / this.prep.total) * 100) : 0;
                return 100;
            },
            get clock() {
                const s = Math.ceil(this.remaining);
                return `${pad(Math.floor(s / 60))}:${pad(s % 60)}`;
            },
            get phase() {
                if (this.status === 'cancelled') return 'cancelled';
                if (this.status === 'pending') return 'queued';
                if (this.status === 'preparing') return 'cooking';
                if (this.status === 'completed') return 'done';
                return 'ready';
            },
            get headline() {
                if (this.status === 'preparing') return this.clock;
                if (this.status === 'pending') return `~${Math.round(this.prep.total / 60)} min`;
                return 'Done';
            },
            get note() {
                if (this.status === 'pending') return 'Waiting for the chef to confirm';
                if (this.status === 'preparing') return this.remaining <= 0 ? 'Almost ready' : (this.prep.ready_at ? `Ready around ${this.prep.ready_at}` : 'Cooking now');
                if (this.status === 'ready') return dineIn ? 'Heading to your table' : 'Your food is ready';
                if (this.status === 'out_for_delivery') return 'Out for delivery';
                return '';
            },

            init() {
                window.Echo?.channel('orders.' + this.code).listen('.order.updated', (e) => {
                    this.status = e.status;
                    this.payment = e.payment_status;
                    this.poll();
                });
                setInterval(() => this.poll(), 20000);
                timer = setInterval(() => {
                    this.now = Date.now();
                    this.pushProgress();
                }, 500);
                if (scene3d) this.boot3d(scene3d);
            },

            destroy() {
                clearInterval(timer);
                scene?.destroy();
                scene = null;
            },

            apply(d) {
                this.status = d.status;
                this.payment = d.payment_status;
                this.delivery = d.delivery;
                this.prep = d.progress;
                this.steps = d.milestones;
                this.clientAt = Date.now();
                this.now = this.clientAt;
                this.pushProgress();
                this.serveIfReady();
            },

            async poll() {
                try {
                    this.apply(await api('GET', `/track/${this.code}/status`));
                } catch (_) {}
            },

            async markServed() {
                try {
                    this.apply(await api('POST', `/track/${this.code}/served`));
                } catch (_) { }
            },

            actors() {
                return scene?.actorsInfo() ?? [];
            },

            serveIfReady() {
                if (!scene3d || !scene3d.tableId || this.status !== 'ready') return;
                scene?.serveOrders([{ key: this.code, tableId: scene3d.tableId, age: 0, onServed: () => this.markServed() }]);
            },

            pushProgress() {
                scene?.setProgress({
                    phase: this.phase,
                    percent: this.percent,
                    time: this.status === 'preparing' ? this.clock : (this.status === 'pending' ? `~${Math.round(this.prep.total / 60)}m` : (this.phase === 'cancelled' ? '' : 'DONE')),
                    title: this.code,
                    note: this.note,
                });
            },

            async boot3d(cfg) {
                try {
                    const { createScene } = await import('./reservation3d');
                    scene = createScene(this.$refs.stage, JSON.parse(JSON.stringify(cfg.floors)), {});
                    scene.setAutoRotate(false);
                    scene.setStates({}, false);
                    if (cfg.tableId) scene.setSelected(cfg.tableId);
                    scene.focusKitchen(true);
                    this.pushProgress();
                    this.serveIfReady();
                } catch (e) {
                    this.fail3d = true;
                } finally {
                    this.loading3d = false;
                }
            },
        };
    });

    Alpine.data('reservationPage', ({ floors, config }) => {
        let scene = null;
        let hintTimer = null;
        let feedTimer = null;

        return {
            floors, config,
            floorId: floors[0]?.id ?? null,
            date: config.min,
            time: '',
            party: 2,
            slots: [],
            states: {},
            selected: null,
            name: config.me?.name ?? '', phone: '', email: config.me?.email ?? '', notes: '',
            payMethod: 'cash',
            me: !!config.me,
            remembered: false,
            error: '', busy: false,

            view: '3d',
            focus: null,
            loading3d: true,
            fail3d: false,
            autoRotate: true,
            tip: null,
            hint: '',
            exploded: false,
            fullscreen: false,

            get floor() { return this.floors.find((f) => f.id === this.floorId); },
            get selectedTable() { return this.tableById(this.selected); },
            get price() {
                const sub = Math.round(this.config.fee * this.party * 100) / 100;
                const tax = Math.round(sub * this.config.taxRate * 100) / 100;
                return { sub, tax, total: Math.round((sub + tax) * 100) / 100 };
            },
            get detailsReady() {
                return !!(this.selectedTable && this.time && this.name.trim() && this.phone.trim().length >= 7
                    && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email.trim()));
            },
            money(n) { return '$' + Number(n).toFixed(2); },
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
                if (!this.me) {
                    try {
                        const saved = JSON.parse(localStorage.getItem('kusina.booker') || 'null');
                        if (saved && (saved.name || saved.phone || saved.email)) {
                            this.email = String(saved.email || '').slice(0, 150);
                            this.name = String(saved.name || '').slice(0, 100);
                            this.phone = String(saved.phone || '').slice(0, 20);
                            this.remembered = true;
                        }
                    } catch (_) { }
                }
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
                clearInterval(feedTimer);
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
                    const feed = async () => {
                        try {
                            const d = await api('GET', '/kitchen/feed');
                            scene?.serveOrders(d.ready.map((o) => ({ key: o.key, tableId: o.table_id, age: o.age })));
                        } catch (_) { }
                    };
                    feed();
                    feedTimer = setInterval(feed, 8000);
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
                    if (this.time && this.selected && this.states[this.selected] !== 'available') {
                        const t = this.selectedTable;
                        this.flash(t ? `Table ${t.label} isn't free at that time. Please pick another table.` : '');
                        this.selected = null;
                    }
                } catch (_) {}
            },

            stateOf(t) { return this.time ? (this.states[t.id] || 'booked') : 'idle'; },
            pick(t) { if (['available', 'idle'].includes(this.stateOf(t))) this.selected = t.id; },

            pickId(id) {
                const t = this.tableById(id);
                if (!t) return;
                const st = this.stateOf(t);
                if (st === 'available' || st === 'idle') {
                    this.selected = id;
                    this.floorId = this.floorOf(id)?.id ?? this.floorId;
                    this.hint = st === 'idle' ? `Table ${t.label} selected. Now choose a time.` : '';
                    if (st === 'idle') { clearTimeout(hintTimer); hintTimer = setTimeout(() => { this.hint = ''; }, 3500); }
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
                    const pay = Object.fromEntries(['payment_method', 'payment_detail', 'account_number', 'card_holder', 'card_number', 'card_expiry']
                        .map((k) => [k, this.$refs.details.elements[k]?.value ?? null]));
                    const checked = this.$refs.details.querySelector('input[name=payment_method]:checked');
                    pay.payment_method = checked ? checked.value : this.payMethod;
                    const d = await api('POST', '/reservations', {
                        table_id: this.selected, date: this.date, time: this.time, party_size: this.party,
                        customer_name: this.name, customer_phone: this.phone, customer_email: this.email, notes: this.notes || null,
                        ...pay,
                    });
                    try { localStorage.setItem('kusina.booker', JSON.stringify({ name: this.name, phone: this.phone, email: this.email })); } catch (_) { }
                    window.rememberReservation({ code: d.redirect.split('/').pop(), table: this.selectedTable?.label, when: `${this.date} ${this.time}` });
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
            setInterval(() => this.load(), 15000);
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

    Alpine.data('reservationBoard', () => ({
        init() { window.Echo?.private('staff'); window.Echo?.channel('reservations').listen('.reservations.changed', () => window.location.reload()); },
    }));

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

    Alpine.data('itemForm', ({ groups }) => ({
        groups,
        addGroup() { this.groups.push({ name: '', type: 'single', is_required: false, max_select: 0, options: [{ name: '', price_delta: 0, is_available: true }] }); },
        removeGroup(i) { this.groups.splice(i, 1); },
        addOption(g) { g.options.push({ name: '', price_delta: 0, is_available: true }); },
        removeOption(g, i) { g.options.splice(i, 1); },
    }));

    Alpine.data('checkoutPage', ({ type, pay, quote, address, reservation }) => ({
        type, pay, quote, address, reservation,
        bookings: [],
        codeInput: '',
        codes: [],
        codeMsg: '',
        busy: false,

        init() {
            const fixPay = () => {
                const dine = this.type === 'dine_in';
                if (dine && !['cash', 'ewallet', 'card'].includes(this.pay)) this.pay = 'cash';
                if (!dine && !['counter', 'online'].includes(this.pay)) this.pay = 'counter';
            };
            fixPay();
            if (!this.reservation) {
                try { this.reservation = localStorage.getItem('kusina.reservation') || ''; } catch (_) {}
            try { this.bookings = JSON.parse(localStorage.getItem('kusina.reservations') || '[]'); } catch (_) { this.bookings = []; }
            }
            this.$watch('type', () => { fixPay(); this.refresh(); });
            this.$watch('address', () => { if (this.type === 'delivery') this.refresh(); });
            if (this.type === 'delivery' && this.address) this.refresh();
        },
        body() {
            return { order_type: this.type, codes: this.codes, delivery_address: this.type === 'delivery' ? this.address : null };
        },

        async refresh() {
            try {
                this.quote = await api('POST', '/checkout/quote', this.body());
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

    Alpine.data('featureSlider', ({ count }) => ({
        i: 0,
        count,
        paused: false,
        startX: null,

        init() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            setInterval(() => { if (!this.paused) this.next(); }, 7000);
        },
        go(n) { this.i = (n + this.count) % this.count; },
        next() { this.go(this.i + 1); },
        prev() { this.go(this.i - 1); },

        touchStart(e) { this.startX = e.touches[0].clientX; },
        touchEnd(e) {
            if (this.startX === null) return;
            const dx = e.changedTouches[0].clientX - this.startX;
            if (Math.abs(dx) > 50) (dx < 0 ? this.next() : this.prev());
            this.startX = null;
        },
    }));}
