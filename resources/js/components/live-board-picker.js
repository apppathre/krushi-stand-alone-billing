import Alpine from 'alpinejs';

/**
 * Live stock TV board — pick which active products show.
 * Registered as Alpine.data so drawer HTML loaded via x-html can init
 * (inline <script> in drawer fragments does not run).
 */
Alpine.data('liveBoardPicker', (cfg = {}) => ({
    items: (cfg.items || []).map((it) => ({
        id: it.id,
        code: it.code || '',
        name: it.name || '',
        packing: it.packing || '',
        category_id: it.category_id,
        category: it.category || '',
        show: !!it.show,
    })),
    categories: cfg.categories || [],
    q: '',
    categoryId: '',

    matches(it) {
        if (this.categoryId && String(it.category_id) !== String(this.categoryId)) {
            return false;
        }
        const q = this.q.trim().toLowerCase();
        if (!q) return true;
        return (
            (it.name || '').toLowerCase().includes(q) ||
            (it.code || '').toLowerCase().includes(q) ||
            (it.packing || '').toLowerCase().includes(q) ||
            (it.category || '').toLowerCase().includes(q)
        );
    },

    get available() {
        return this.items.filter((it) => !it.show && this.matches(it));
    },

    get selected() {
        return this.items.filter((it) => it.show && this.matches(it));
    },

    get selectedIds() {
        return this.items.filter((it) => it.show).map((it) => it.id);
    },

    get onCount() {
        return this.selectedIds.length;
    },

    addOne(id) {
        const it = this.items.find((x) => String(x.id) === String(id));
        if (it) it.show = true;
    },

    removeOne(id) {
        const it = this.items.find((x) => String(x.id) === String(id));
        if (it) it.show = false;
    },

    addFiltered() {
        this.available.forEach((it) => {
            it.show = true;
        });
    },

    removeFiltered() {
        this.selected.forEach((it) => {
            it.show = false;
        });
    },

    addAll() {
        this.items.forEach((it) => {
            it.show = true;
        });
    },

    clearAll() {
        this.items.forEach((it) => {
            it.show = false;
        });
    },
}));
