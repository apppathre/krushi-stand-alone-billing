import Alpine from 'alpinejs';
import { enToMr, enToMrOnline } from './en-to-mr';

/** Excel-style multi-row product entry (paste TSV or type rows). */
Alpine.data('bulkItems', (cfg) => ({
    categories: cfg.categories || [],
    manufacturers: cfg.manufacturers || [],
    units: cfg.units || [],
    gstSlabs: cfg.gstSlabs || ['0', '5', '12', '18', '28'],
    rows: [],
    pasteOpen: false,
    pasteText: '',

    blank() {
        return {
            key: Math.random().toString(36).slice(2),
            code: '', name: '', name_mr: '', _mrManual: false, category_id: '', manufacturer_code: '',
            unit_id: '', packing: '', marketed_by: '', usage: '', hsn_code: '',
            gst_rate: '5', purchase_rate: '', sale_rate: '', mrp: '', reorder_level: '',
            batch_required: '1',
        };
    },

    onName(row) {
        if (row._mrManual) return;
        row.name_mr = enToMr(row.name || '');
        clearTimeout(row._mrTimer);
        const typed = row.name || '';
        row._mrTimer = setTimeout(async () => {
            if (row._mrManual) return;
            row.name_mr = await enToMrOnline(typed);
        }, 320);
    },

    onNameMr(row) {
        row._mrManual = (row.name_mr || '').trim() !== '' && row.name_mr !== enToMr(row.name || '');
    },

    init() {
        this.add(5);
    },

    add(n = 1) {
        for (let i = 0; i < n; i++) {
            this.rows.push(this.blank());
        }
    },

    remove(i) {
        if (this.rows.length > 1) {
            this.rows.splice(i, 1);
        }
    },

    focusPaste() {
        this.pasteOpen = true;
        this.$nextTick(() => this.$refs.pasteBox?.focus());
    },

    matchCat(v) {
        if (!v) return '';
        const s = String(v).trim().toLowerCase();
        const byId = this.categories.find((c) => String(c.id) === s);
        if (byId) return String(byId.id);
        const byName = this.categories.find((c) => c.name.toLowerCase() === s || c.name.toLowerCase().includes(s));

        return byName ? String(byName.id) : '';
    },

    matchUnit(v) {
        if (!v) return '';
        const s = String(v).trim().toLowerCase();
        const byId = this.units.find((u) => String(u.id) === s);
        if (byId) return String(byId.id);
        const byShort = this.units.find((u) => (u.short || '').toLowerCase() === s || u.name.toLowerCase().startsWith(s));

        return byShort ? String(byShort.id) : '';
    },

    applyPaste() {
        const lines = this.pasteText.trim().split(/\r?\n/).filter(Boolean);
        if (!lines.length) return;
        const mapped = lines.map((line) => {
            const c = line.split('\t');
            const r = this.blank();
            r.code = (c[0] || '').trim();
            r.name = (c[1] || '').trim();
            r.name_mr = (c[2] || '').trim() || enToMr(r.name);
            r._mrManual = !!(c[2] || '').trim();
            r.category_id = this.matchCat(c[3]);
            r.manufacturer_code = (c[4] || '').trim().toUpperCase();
            r.unit_id = this.matchUnit(c[5]);
            r.packing = (c[6] || '').trim();
            r.marketed_by = (c[7] || '').trim();
            r.usage = (c[8] || '').trim();
            r.hsn_code = (c[9] || '').trim();
            r.gst_rate = (c[10] || '5').trim() || '5';
            r.purchase_rate = (c[11] || '').trim();
            r.sale_rate = (c[12] || '').trim();
            r.mrp = (c[13] || '').trim();
            r.reorder_level = (c[14] || '').trim();
            const b = (c[15] || 'Y').trim().toUpperCase();
            r.batch_required = (b === 'N' || b === '0' || b === 'NO') ? '0' : '1';

            return r;
        }).filter((r) => r.name);
        if (mapped.length) {
            this.rows = mapped;
            this.pasteOpen = false;
            this.pasteText = '';
        }
    },
}));
