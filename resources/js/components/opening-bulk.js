import Alpine from 'alpinejs';
import { bindProductSelect2 } from './product-select2';

/** Opening stock bulk sheet — all items; only rows with qty post. */
Alpine.data('openingBulk', (cfg) => ({
    products: (cfg.products || []).map((p) => ({
        ...p,
        qty: '',
        batch_no: '',
        mfg_date: '',
        expiry_date: '',
        rate: p.rate != null ? String(p.rate) : '',
        mrp: p.mrp != null ? String(p.mrp) : '',
    })),
    placeId: String(cfg.placeId || ''),
    q: '',
    onlyQty: false,

    visible(p) {
        if (this.onlyQty && !(Number(p.qty) > 0)) return false;
        const q = this.q.trim().toLowerCase();
        if (!q) return true;
        return (
            String(p.name || '')
                .toLowerCase()
                .includes(q) ||
            String(p.code || '')
                .toLowerCase()
                .includes(q)
        );
    },

    metaLabel() {
        const shown = this.products.filter((p) => this.visible(p)).length;
        const withQty = this.products.filter((p) => Number(p.qty) > 0).length;
        return `${shown} shown · ${withQty} with qty · ${this.products.length} items`;
    },

    fillRates() {
        this.products.forEach((p) => {
            if (String(p.rate ?? '').trim() === '') {
                const src = (cfg.products || []).find((x) => String(x.id) === String(p.id));
                if (src) p.rate = String(src.rate ?? '');
            }
            if (String(p.mrp ?? '').trim() === '') {
                const src = (cfg.products || []).find((x) => String(x.id) === String(p.id));
                if (src) p.mrp = String(src.mrp ?? '');
            }
        });
    },
}));

/** Single-item opening stock drawer. */
Alpine.data('openingDrawer', (cfg) => ({
    products: cfg.products || [],
    placeId: String(cfg.placeId || ''),
    productId: '',
    batchNo: '',
    mfgDate: '',
    expiryDate: '',
    qty: '1',
    rate: '',
    mrp: '',

    get product() {
        return this.products.find((p) => String(p.id) === String(this.productId));
    },

    get batchRequired() {
        return !!this.product?.batch_required;
    },

    pick() {
        const p = this.product;
        if (!p) return;
        this.rate = String(p.rate ?? '');
        this.mrp = String(p.mrp ?? '');
        if (!p.batch_required) {
            this.batchNo = '';
            this.mfgDate = '';
            this.expiryDate = '';
        }
    },

    mountProductSelect(el) {
        if (!el) return;
        bindProductSelect2(el, {
            products: this.products,
            value: this.productId,
            placeholder: 'Select product',
            showStock: false,
            onChange: (id) => {
                if (String(this.productId || '') === String(id || '')) return;
                this.productId = id || '';
                this.pick();
            },
        });
    },
}));
