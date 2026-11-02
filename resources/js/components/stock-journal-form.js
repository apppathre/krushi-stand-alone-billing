import Alpine from 'alpinejs';
import { bindProductSelect2, unbindProductSelect2 } from './product-select2';

/**
 * Stock journal desk lines (out / in / physical count).
 * config: { type, products, catalogIn, places, defaultPlaceId, old }
 */
Alpine.data('stockJournalForm', (config) => ({
    type: config.type || 'journal',
    products: config.products || [],
    catalogIn: config.catalogIn || [],
    places: config.places || [],
    defaultPlaceId: String(config.defaultPlaceId || (config.places?.[0]?.id ?? '')),
    lines: [],

    init() {
        const old = config.old && config.old.length ? config.old : [];
        this.lines = old.length ? old.map((l) => this.hydrate(l)) : [this.blank()];
    },

    blank() {
        const side = this.type === 'return' ? 'in' : (this.type === 'journal' ? 'out' : (this.type === 'physical' ? 'count' : 'out'));
        return {
            key: Math.random().toString(36).slice(2),
            godown_id: this.defaultPlaceId,
            side,
            product_id: '', batch_id: '', batch_no: '', mfg_date: '', expiry_date: '',
            qty: '', counted_qty: '', rate: 0,
        };
    },

    hydrate(l) {
        return { ...this.blank(), ...l, godown_id: String(l.godown_id || this.defaultPlaceId) };
    },

    setDefaultPlace(id) {
        this.defaultPlaceId = String(id || '');
        // Transfer / single-place desks: keep every line on the header From place.
        if (this.type === 'transfer' || this.places.length <= 1) {
            this.lines.forEach((line) => {
                line.godown_id = this.defaultPlaceId;
                line.batch_id = '';
            });
        }
    },

    placeId(line) {
        return String(line.godown_id || this.defaultPlaceId);
    },

    catalog(line) {
        if (this.type === 'return' || (this.type === 'journal' && line.side === 'in')) {
            return this.catalogIn;
        }
        return this.products;
    },

    product(line) {
        return this.catalog(line).find((p) => String(p.id) === String(line.product_id))
            || this.products.find((p) => String(p.id) === String(line.product_id));
    },

    batches(line) {
        const gid = this.placeId(line);
        return (this.product(line)?.batches || []).filter((b) => String(b.godown_id) === gid);
    },

    batch(line) {
        return this.batches(line).find((b) => String(b.id) === String(line.batch_id));
    },

    stockPlaces(line) {
        return this.product(line)?.stock_by_place || [];
    },

    stockPlacesLabel(line) {
        const rows = this.stockPlaces(line);
        if (!rows.length) return '';
        return rows.map((r) => `${r.name}: ${r.qty}`).join(' · ');
    },

    available(line) {
        if (line.side === 'in' || this.type === 'return') return null;
        const b = this.batch(line);
        if (b) return b.qty;
        const list = this.batches(line);
        if (list.length) return list.reduce((s, x) => s + Number(x.qty || 0), 0);
        return this.product(line)?.stock ?? null;
    },

    systemQty(line) {
        return this.available(line) ?? 0;
    },

    pick(line) {
        const p = this.product(line);
        if (!p) return;
        line.rate = p.rate || p.mrp || 0;
        if (line.side !== 'in' && this.type !== 'return') {
            const byPlace = p.stock_by_place || [];
            if (byPlace.length && this.places.length > 1 && this.type !== 'transfer') {
                const best = byPlace[0];
                if (best?.godown_id) {
                    line.godown_id = String(best.godown_id);
                }
            }
            const list = this.batches(line);
            line.batch_id = p.batch_required && list.length ? list[0].id : '';
            if (this.type === 'physical') {
                line.counted_qty = this.systemQty(line);
            }
        }
    },

    placeChanged(line) {
        line.batch_id = '';
        this.pick(line);
        this.$nextTick(() => {
            const idx = this.lines.indexOf(line);
            const el = this.$root.querySelectorAll('[data-line]')[idx]?.querySelector('select.js-txn-product');
            this.refreshProductSelect(el, line);
        });
    },

    sideChanged(line) {
        line.product_id = '';
        line.batch_id = '';
        line.batch_no = '';
        line.qty = '';
        line.counted_qty = '';
        this.$nextTick(() => {
            const idx = this.lines.indexOf(line);
            const el = this.$root.querySelectorAll('[data-line]')[idx]?.querySelector('select.js-txn-product');
            this.refreshProductSelect(el, line);
        });
    },

    mountProductSelect(el, line) {
        if (!el || !line) return;
        const list = this.catalog(line);
        const showStock = list.some((p) => p.stock != null);
        bindProductSelect2(el, {
            products: list,
            value: line.product_id,
            placeId: this.placeId(line),
            placeholder: 'Select product',
            showStock,
            onChange: (id) => {
                if (String(line.product_id || '') === String(id || '')) return;
                line.product_id = id || '';
                if (line.product_id) {
                    this.pick(line);
                } else {
                    line.batch_id = '';
                }
            },
        });
    },

    refreshProductSelect(el, line) {
        if (!el || !line) return;
        this.mountProductSelect(el, line);
    },

    destroyProductSelect(el) {
        unbindProductSelect2(el);
    },

    add() {
        this.lines.push(this.blank());
    },

    remove(i) {
        if (this.lines.length <= 1) return;
        const rows = this.$root.querySelectorAll('[data-line]');
        const el = rows[i]?.querySelector('select.js-txn-product');
        this.destroyProductSelect(el);
        this.lines.splice(i, 1);
    },
}));
