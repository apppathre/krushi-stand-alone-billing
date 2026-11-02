import Alpine from 'alpinejs';

const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

/**
 * Line-item form used by sales invoices, purchases and opening stock.
 * The server recomputes every figure (GstCalculator); this only gives live feedback.
 *
 * config: { mode, products, places, defaultPlaceId, racksByPlace, gstRates, parties,
 *           creditDays, defaultCreditDays, voucherDate, companyState, partyId,
 *           supplyState, old, paid, settlement }
 */
Alpine.data('invoiceForm', (config) => ({
    mode: config.mode,
    products: config.products || [],
    places: config.places || [],
    defaultPlaceId: String(config.defaultPlaceId || (config.places?.[0]?.id ?? '')),
    racksByPlace: config.racksByPlace || {},
    gstRates: (config.gstRates || [0, 5, 12, 18, 28]).map(Number),
    parties: config.parties || {},
    creditDays: config.creditDays || {},
    defaultCreditDays: Number(config.defaultCreditDays ?? 30),
    creditDaysBill: config.creditDaysBill !== undefined && config.creditDaysBill !== null && config.creditDaysBill !== ''
        ? Number(config.creditDaysBill)
        : null,
    voucherDate: config.voucherDate || '',
    cashDiscount: Number(config.cashDiscount || 0),
    freightAmount: Number(config.freightAmount || 0),
    hammaliAmount: Number(config.hammaliAmount || 0),
    companyState: config.companyState || '',
    partyId: config.partyId || '',
    supplyState: config.supplyState || config.companyState || '',
    settlement: config.settlement || (Number(config.paid || 0) > 0 ? 'cash' : 'credit'),
    paid: config.paid || 0,
    lines: [],

    init() {
        const old = config.old && config.old.length ? config.old : [];
        this.lines = old.length ? old.map((l) => this.hydrate(l)) : [this.blank()];
        if (this.partyId) {
            this.partyChanged(true);
        } else if (this.creditDaysBill === null) {
            this.creditDaysBill = this.defaultCreditDays;
        }
        this.onSettlementChange(true);
    },

    onSettlementChange(silent = false) {
        if (this.settlement === 'credit') {
            this.paid = 0;
        } else if (this.settlement === 'cash' && !silent && Number(this.paid || 0) <= 0) {
            this.paid = this.totals.grand;
        }
    },

    /** Master credit days for selected party (party master, else app default). */
    partyCreditDays() {
        if (!this.partyId) return this.defaultCreditDays;
        const raw = this.creditDays[this.partyId] ?? this.creditDays[String(this.partyId)];
        if (raw === null || raw === undefined || raw === '') return this.defaultCreditDays;
        const n = Number(raw);
        return Number.isFinite(n) && n >= 0 ? n : this.defaultCreditDays;
    },

    /** Bill-level credit days (editable; seeded from party). */
    billCreditDays() {
        const n = Number(this.creditDaysBill);
        if (Number.isFinite(n) && n >= 0) return Math.min(365, Math.max(0, Math.round(n)));
        return this.partyCreditDays();
    },

    get dueDateIso() {
        const base = this.voucherDate || '';
        if (!base) return '';
        const d = new Date(`${base}T00:00:00`);
        if (Number.isNaN(d.getTime())) return '';
        d.setDate(d.getDate() + this.billCreditDays());
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    },

    /** e.g. "due Mon, 02 Dec 2026" */
    get creditDueLabel() {
        if (this.settlement !== 'credit') return '';
        const days = this.billCreditDays();
        const iso = this.dueDateIso;
        if (!iso) {
            return days === 1 ? '1 day credit' : `${days} days credit`;
        }
        const d = new Date(`${iso}T00:00:00`);
        const due = d.toLocaleDateString('en-IN', {
            weekday: 'short',
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
        const dayWord = days === 1 ? '1 day' : `${days} days`;
        return `Due ${due} · ${dayWord}`;
    },

    blank() {
        const place = this.defaultPlaceId;
        return {
            key: Math.random().toString(36).slice(2),
            godown_id: place,
            rack_id: this.mode === 'purchase' ? this.defaultRackId(place) : '',
            product_id: '', batch_id: '', batch_no: '', mfg_date: '', expiry_date: '',
            qty: 1, rate: 0, mrp: 0, discount_pct: 0,
            gst: 0, gst_default: 0, gst_overridden: false,
        };
    },

    hydrate(l) {
        const line = { ...this.blank(), ...l };
        line.godown_id = String(line.godown_id || this.defaultPlaceId);
        line.rack_id = line.rack_id ? String(line.rack_id) : (this.mode === 'purchase' ? this.defaultRackId(line.godown_id) : '');
        const p = this.product(line);
        const def = p ? Number(p.gst) : Number(line.gst_default || line.gst || 0);
        line.gst_default = def;
        if (l.gst_rate !== undefined && l.gst_rate !== null && l.gst_rate !== '') {
            line.gst = Number(l.gst_rate);
            line.gst_overridden = Math.abs(line.gst - def) > 0.001;
        } else if (l.gst !== undefined && l.gst !== null && l.gst !== '') {
            line.gst = Number(l.gst);
            line.gst_overridden = Math.abs(line.gst - def) > 0.001;
        } else {
            line.gst = def;
            line.gst_overridden = false;
        }
        return line;
    },

    product(line) {
        return this.products.find((p) => String(p.id) === String(line.product_id));
    },

    placeId(line) {
        return String(line.godown_id || this.defaultPlaceId);
    },

    batches(line) {
        const gid = this.placeId(line);
        return (this.product(line)?.batches || []).filter((b) => String(b.godown_id) === gid);
    },

    batch(line) {
        return this.batches(line).find((b) => String(b.id) === String(line.batch_id));
    },

    racks(line) {
        return this.racksByPlace[this.placeId(line)] || this.racksByPlace[Number(this.placeId(line))] || [];
    },

    /** Default rack for a place (is_default or first). */
    defaultRackId(placeId) {
        const list = this.racksByPlace[String(placeId)] || this.racksByPlace[Number(placeId)] || [];
        if (!list.length) return '';
        const pref = list.find((r) => r.is_default) || list[0];
        return String(pref.id);
    },

    placeStock(line) {
        return round2(this.batches(line).reduce((s, b) => s + Number(b.qty || 0), 0) * 1000) / 1000;
    },

    /** Stock breakdown by place for the selected product (sale). */
    stockPlaces(line) {
        return this.product(line)?.stock_by_place || [];
    },

    stockPlacesLabel(line) {
        const rows = this.stockPlaces(line);
        if (!rows.length) return '';
        return rows.map((r) => `${r.name}: ${r.qty}`).join(' · ');
    },

    available(line) {
        if (this.mode !== 'sale') return null;
        const b = this.batch(line);
        if (b) return b.qty;
        return this.product(line) ? this.placeStock(line) : null;
    },

    overStock(line) {
        const a = this.available(line);

        return a !== null && Number(line.qty) > a + 0.0005;
    },

    setDefaultPlace(id) {
        this.defaultPlaceId = String(id || '');
        // Purchase: moving header place also sets default put-away rack on empty lines.
        if (this.mode === 'purchase') {
            const rack = this.defaultRackId(this.defaultPlaceId);
            this.lines.forEach((line) => {
                line.godown_id = this.defaultPlaceId;
                if (!line.rack_id || !this.racks(line).some((r) => String(r.id) === String(line.rack_id))) {
                    line.rack_id = rack;
                }
            });
        }
    },

    placeChanged(line) {
        line.batch_id = '';
        if (this.mode === 'purchase') {
            line.rack_id = this.defaultRackId(line.godown_id);
        } else {
            line.rack_id = '';
        }
        if (this.mode === 'sale') {
            const p = this.product(line);
            if (p?.batch_required) {
                const first = this.batches(line)[0];
                line.batch_id = first ? first.id : '';
            }
        }
    },

    pick(line) {
        const p = this.product(line);
        if (!p) return;
        line.rate = p.rate;
        line.mrp = p.mrp;
        line.gst_default = Number(p.gst || 0);
        line.gst = line.gst_default;
        line.gst_overridden = false;
        if (this.mode === 'sale') {
            // Prefer the place that still has the most stock for this item.
            const byPlace = p.stock_by_place || [];
            if (byPlace.length) {
                const best = byPlace[0];
                if (best?.godown_id) {
                    line.godown_id = String(best.godown_id);
                }
            }
            const list = this.batches(line);
            line.batch_id = p.batch_required && list.length ? list[0].id : '';
            if (list[0]?.mrp) line.mrp = list[0].mrp;
        }
        if (this.mode === 'purchase' && !line.rack_id) {
            line.rack_id = this.defaultRackId(line.godown_id);
        }
    },

    pickBatch(line) {
        const b = this.batch(line);
        if (b && b.mrp) line.mrp = b.mrp;
    },

    rememberGst(line) {
        line._gstPrev = Number(line.gst);
    },

    /**
     * Change GST on a line. Default stays product rate until user confirms an override.
     */
    changeGst(line) {
        const next = Number(line.gst);
        const prev = line._gstPrev !== undefined ? Number(line._gstPrev) : Number(line.gst_default || 0);
        if (Number.isNaN(next) || next < 0 || next > 100) {
            line.gst = prev;
            return;
        }
        if (Math.abs(next - prev) < 0.001) {
            return;
        }
        const def = Number(line.gst_default || 0);
        const leavingDefault = Math.abs(prev - def) < 0.001 && Math.abs(next - def) > 0.001;
        const msg = leavingDefault
            ? `Change GST from product default ${def}% to ${next}% for this line only?\n\nProduct master GST stays ${def}%.`
            : `Change GST from ${prev}% to ${next}% for this line?`;
        if (!window.confirm(msg)) {
            line.gst = prev;
            return;
        }
        line.gst_overridden = Math.abs(next - def) > 0.001;
    },

    resetGst(line) {
        if (!line.gst_overridden) return;
        if (!window.confirm(`Reset GST to product default ${line.gst_default}%?`)) {
            return;
        }
        line.gst = Number(line.gst_default || 0);
        line.gst_overridden = false;
    },

    add() {
        this.lines.push(this.blank());
        this.$nextTick(() => {
            const rows = this.$root.querySelectorAll('[data-line]');
            rows[rows.length - 1]?.querySelector('select')?.focus();
        });
    },

    remove(i) {
        if (this.lines.length > 1) this.lines.splice(i, 1);
    },

    get inter() {
        return !!this.supplyState && !!this.companyState && this.supplyState !== this.companyState;
    },

    partyChanged(keep = false) {
        const state = this.parties[this.partyId];
        if (!keep || state) {
            this.supplyState = state || this.companyState;
        }
        // Refresh bill credit days from party (user can still edit for this bill).
        if (!keep || this.creditDaysBill === null || this.creditDaysBill === '') {
            this.creditDaysBill = this.partyCreditDays();
        } else if (!keep) {
            this.creditDaysBill = this.partyCreditDays();
        }
        if (!keep && this.settlement !== 'cash') {
            this.settlement = 'credit';
            this.paid = 0;
        }
    },

    setPartyCreditDays(id, days) {
        if (!id) return;
        const n = days === null || days === undefined || days === '' ? null : Number(days);
        this.creditDays[String(id)] = Number.isFinite(n) ? n : null;
    },

    calc(line) {
        const gross = round2(Number(line.qty || 0) * Number(line.rate || 0));
        const disc = round2((gross * Math.min(100, Math.max(0, Number(line.discount_pct || 0)))) / 100);
        const taxable = round2(gross - disc);
        const tax = round2((taxable * Number(line.gst || 0)) / 100);
        const half = round2(tax / 2);

        return {
            gross, disc, taxable, tax,
            cgst: this.inter ? 0 : half,
            sgst: this.inter ? 0 : round2(tax - half),
            igst: this.inter ? tax : 0,
            total: round2(taxable + tax),
        };
    },

    get totals() {
        const t = { gross: 0, disc: 0, taxable: 0, cgst: 0, sgst: 0, igst: 0 };
        this.lines.forEach((l) => {
            if (!l.product_id) return;
            const c = this.calc(l);
            Object.keys(t).forEach((k) => (t[k] = round2(t[k] + c[k])));
        });
        t.freight = Math.max(0, round2(Number(this.freightAmount || 0)));
        t.hammali = Math.max(0, round2(Number(this.hammaliAmount || 0)));
        let cash = Math.max(0, round2(Number(this.cashDiscount || 0)));
        const goods = round2(t.taxable + t.cgst + t.sgst + t.igst);
        const maxCash = Math.max(0, round2(goods + t.freight + t.hammali));
        if (cash > maxCash) cash = maxCash;
        t.cash = cash;
        const before = round2(goods + t.freight + t.hammali - t.cash);
        t.grand = Math.round(before);
        t.round = round2(t.grand - before);
        t.balance = round2(t.grand - Number(this.paid || 0));

        return t;
    },

    money(n) {
        return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    payFull() {
        this.settlement = 'cash';
        this.paid = this.totals.grand;
    },
}));
