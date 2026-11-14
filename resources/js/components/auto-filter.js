import Alpine from 'alpinejs';

/**
 * Auto-filter for list screens.
 *
 * Usage (see resources/views/components/auto-filter.blade.php):
 *   <form x-data="autoFilter('#list-results')" ...> inputs </form>
 *   <div id="list-results"> ...table + pagination... </div>
 *
 * Typing / changing any input re-fetches the same page with the form values as query string
 * and swaps only the results container, so the URL stays shareable and no full reload happens.
 */
Alpine.data('autoFilter', (target = '#list-results') => ({
    controller: null,
    loading: false,

    init() {
        // Pagination links inside the results container are loaded in place too.
        this._onClick = (e) => {
            const root = this.$el.closest('#ui-drawer-body') || document;
            const container = root.querySelector?.(target) || document.querySelector(target);
            if (!container || !container.contains(e.target)) return;
            const link = e.target.closest('a[href]');
            if (!link || link.target === '_blank' || !/[?&]page=/.test(link.href)) {
                return;
            }
            e.preventDefault();
            try {
                const u = new URL(link.href, window.location.origin);
                u.searchParams.set('list', '1');
                this.load(u.pathname + u.search);
            } catch (_) {
                this.load(link.href);
            }
        };
        document.addEventListener('click', this._onClick);
    },

    destroy() {
        document.removeEventListener('click', this._onClick);
    },

    pad(n) {
        return String(n).padStart(2, '0');
    },

    ymd(d) {
        return `${d.getFullYear()}-${this.pad(d.getMonth() + 1)}-${this.pad(d.getDate())}`;
    },

    setRange(preset) {
        const from = this.$el.querySelector('[name="from"]');
        const to = this.$el.querySelector('[name="to"]');
        if (!from || !to) return;

        const now = new Date();
        const today = this.ymd(now);
        if (preset === 'yesterday') {
            const y = new Date(now);
            y.setDate(y.getDate() - 1);
            const s = this.ymd(y);
            from.value = s;
            to.value = s;
        } else if (preset === 'month') {
            from.value = `${now.getFullYear()}-${this.pad(now.getMonth() + 1)}-01`;
            to.value = today;
        } else if (preset === 'fy') {
            from.value = this.$el.getAttribute('data-fy-start') || `${now.getFullYear()}-04-01`;
            to.value = today;
        } else {
            from.value = today;
            to.value = today;
        }
        this.run();
    },

    url() {
        const form = this.$el;
        const params = new URLSearchParams();
        new FormData(form).forEach((value, key) => {
            if (key !== '_token' && String(value).trim() !== '') {
                params.append(key, value);
            }
        });
        // Ask controllers for the table-only partial (drawer open must get full browse).
        if (! params.has('list')) {
            params.set('list', '1');
        }
        const base = form.getAttribute('action') || window.location.pathname;
        const qs = params.toString();

        return qs ? `${base}?${qs}` : base;
    },

    run() {
        return this.load(this.url());
    },

    reset() {
        this.$el.reset();
        this.$el.querySelectorAll('input:not([type=hidden]):not([type=checkbox]), select').forEach((el) => {
            el.value = '';
        });
        this.setRange('month');
    },

    resultsRoot() {
        // Prefer the list inside the same drawer / form scope (avoid wrong page match).
        const drawer = this.$el.closest('#ui-drawer-body');
        if (drawer) {
            return drawer.querySelector(target);
        }
        return document.querySelector(target);
    },

    inDrawer() {
        return !!this.$el.closest('#ui-drawer-body');
    },

    async load(url) {
        const container = this.resultsRoot();
        if (!container) {
            window.location.href = url;
            return;
        }

        this.controller?.abort();
        this.controller = new AbortController();
        this.loading = true;
        container.classList.add('is-loading');

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                signal: this.controller.signal,
                credentials: 'same-origin',
            });
            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            let fresh = doc.querySelector(target);
            if (!fresh) {
                // Partial response whose root is the results node.
                const root = doc.body?.firstElementChild;
                if (root && (root.id === target.replace('#', '') || root.matches?.(target))) {
                    fresh = root;
                }
            }

            if (!fresh) {
                window.location.href = url; // e.g. session expired -> login page
                return;
            }
            container.innerHTML = fresh.innerHTML;
            // Do not rewrite the desk URL while filtering inside a drawer.
            if (! this.inDrawer()) {
                window.history.replaceState({}, '', url);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                window.location.href = url;
            }
        } finally {
            this.loading = false;
            container.classList.remove('is-loading');
        }
    },
}));
