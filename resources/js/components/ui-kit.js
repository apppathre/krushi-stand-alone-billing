/**
 * Alpine store: side drawer + toast for AJAX masters (TMS/ERP-style without full page loads).
 */
export function registerUiKit(Alpine) {
    Alpine.store('ui', {
        drawerOpen: false,
        drawerTitle: '',
        drawerWidth: '32rem',
        drawerHtml: '',
        drawerLoading: false,
        toasts: [],
        _toastId: 0,
        confirmOpen: false,
        confirmMessage: '',
        confirmBusy: false,
        _confirmResolve: null,

        openDrawer({ title = '', width = '32rem', html = '' } = {}) {
            this.drawerTitle = title;
            this.drawerWidth = width;
            this.drawerHtml = html;
            this.drawerLoading = false;
            this.drawerOpen = true;
            document.documentElement.classList.add('overflow-hidden');
        },

        closeDrawer() {
            this.drawerOpen = false;
            this.drawerHtml = '';
            this.drawerLoading = false;
            document.documentElement.classList.remove('overflow-hidden');
        },

        setDrawerLoading(on) {
            this.drawerLoading = !!on;
            if (on) {
                this.drawerHtml = '<div class="flex items-center justify-center gap-2 py-16 text-ink-soft"><span class="ui-spinner"></span> Loading…</div>';
            }
        },

        toast(message, type = 'success') {
            const id = ++this._toastId;
            this.toasts.push({ id, message, type });
            setTimeout(() => this.dismissToast(id), 4200);
        },

        dismissToast(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },

        askConfirm(message) {
            return new Promise((resolve) => {
                this.confirmMessage = message || 'Are you sure?';
                this.confirmBusy = false;
                this._confirmResolve = resolve;
                this.confirmOpen = true;
            });
        },

        resolveConfirm(ok) {
            if (this.confirmBusy) return;
            this.confirmBusy = true;
            this.confirmOpen = false;
            const resolve = this._confirmResolve;
            this._confirmResolve = null;
            this.confirmBusy = false;
            if (resolve) resolve(!!ok);
        },

        async openForm(url, title = 'Form', width = '32rem') {
            this.openDrawer({ title, width });
            this.setDrawerLoading(true);
            try {
                const res = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-UI-Drawer': '1',
                        Accept: 'text/html',
                    },
                });
                if (!res.ok) throw new Error('Failed to load form');
                this.drawerHtml = await res.text();
                this.drawerLoading = false;
                // Wait for Alpine x-html to paint drawer body, then bind forms / name→Marathi.
                setTimeout(() => {
                    bindAjaxFormInDrawer();
                    window.KrushiNameMr?.bind(document.getElementById('ui-drawer-body'));
                }, 30);
            } catch (e) {
                this.closeDrawer();
                this.toast(e.message || 'Could not open form', 'error');
            }
        },
    });

    async function submitAjaxForm(form) {
        const ui = Alpine.store('ui');
        const btn = form.querySelector('[type="submit"]');
        if (form.getAttribute('data-busy') === '1') return;
        if (window.FormBusy) {
            window.FormBusy.set(form, btn, btn?.getAttribute('data-busy-label') || 'Saving…');
        } else if (btn) {
            btn.disabled = true;
        }
        try {
            const fd = new FormData(form);
            const method = (form.querySelector('input[name="_method"]')?.value || form.method || 'POST').toUpperCase();
            const res = await fetch(form.action, {
                method: method === 'GET' ? 'GET' : 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-UI-Drawer': '1',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = data.message || Object.values(data.errors || {}).flat().join(' ') || 'Could not save.';
                ui.toast(msg, 'error');
                const box = form.querySelector('[data-form-errors]');
                if (box) {
                    box.hidden = false;
                    box.textContent = msg;
                }
                return;
            }
            ui.toast(data.message || 'Saved.', 'success');
            ui.closeDrawer();
            if (data.party) {
                window.dispatchEvent(new CustomEvent('krushi-party-saved', { detail: data.party }));
                return;
            }
            if (data.reload !== false) {
                if (window.FormBusy) window.FormBusy.showOverlay('Loading…');
                window.location.reload();
                return;
            }
        } catch (err) {
            ui.toast(err.message || 'Network error', 'error');
        } finally {
            if (window.FormBusy) window.FormBusy.clear(form);
            else if (btn) btn.disabled = false;
        }
    }

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-confirm-submit]');
        if (!btn) return;
        e.preventDefault();
        const ui = Alpine.store('ui');
        const ok = await ui.askConfirm(btn.getAttribute('data-confirm-message') || 'Are you sure?');
        if (!ok) return;

        const action = btn.getAttribute('data-confirm-action');
        const method = (btn.getAttribute('data-confirm-method') || 'POST').toUpperCase();
        if (!action) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        form.style.display = 'none';
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        form.innerHTML = `<input type="hidden" name="_token" value="${token}">`
            + (method !== 'POST' ? `<input type="hidden" name="_method" value="${method}">` : '');
        document.body.appendChild(form);
        if (window.FormBusy) window.FormBusy.showOverlay('Working…');
        form.submit();
    });

    function bindAjaxFormInDrawer() {
        const root = document.getElementById('ui-drawer-body');
        if (!root) return;
        const form = root.querySelector('form[data-ajax-form]');
        if (!form || form.dataset.bound === '1') return;
        form.dataset.bound = '1';
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            submitAjaxForm(form);
        });
    }

    Alpine.data('portalShell', (menuIndex = [], activeGroup = '', activeNested = '') => ({
        navOpen: false,
        mini: localStorage.getItem('krushi.sidebar.mini') === '1',
        searchOpen: false,
        searchQ: '',
        menuIndex,
        activeGroup: activeGroup || '',
        activeNested: activeNested || '',
        /** accordion = one top parent; nested dropdown stays open with active route */
        navMode: localStorage.getItem('krushi.nav.mode') || 'accordion',
        openGroups: JSON.parse(localStorage.getItem('krushi.nav.groups') || '{}'),

        init() {
            if (this.navMode === 'accordion') {
                this.openGroups = this.forcedOpenMap();
                localStorage.setItem('krushi.nav.groups', JSON.stringify(this.openGroups));
            }

            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    this.searchOpen = true;
                    this.$nextTick(() => this.$refs.menuSearch?.focus());
                }
                if (e.key === 'Escape') {
                    this.searchOpen = false;
                    if (Alpine.store('ui').drawerOpen) Alpine.store('ui').closeDrawer();
                }
            });

            window.addEventListener('krushi:nav-mode', (e) => {
                const mode = e.detail === 'remember' ? 'remember' : 'accordion';
                this.setNavMode(mode);
            });
        },

        /** Parent + nested keys that must stay open for the current route. */
        forcedOpenMap() {
            const next = {};
            if (this.activeGroup) {
                next[this.activeGroup] = true;
            }
            if (this.activeNested) {
                next[this.activeNested] = true;
            }
            return next;
        },

        toggleMini() {
            this.mini = !this.mini;
            localStorage.setItem('krushi.sidebar.mini', this.mini ? '1' : '0');
        },

        setNavMode(mode) {
            this.navMode = mode === 'remember' ? 'remember' : 'accordion';
            localStorage.setItem('krushi.nav.mode', this.navMode);
            if (this.navMode === 'accordion') {
                this.openGroups = this.forcedOpenMap();
                localStorage.setItem('krushi.nav.groups', JSON.stringify(this.openGroups));
            }
        },

        /**
         * @param {string} key
         * @param {string|null} parentKey  when set, this is a 2nd-level dropdown under parentKey
         */
        toggleGroup(key, parentKey = null) {
            const willOpen = !this.isGroupOpen(key);
            if (this.navMode === 'accordion') {
                if (parentKey) {
                    // Nested: keep parent open; open/close this submenu (force-open if route is inside it).
                    const next = { [parentKey]: true };
                    if (willOpen || (this.activeNested && this.activeNested === key)) {
                        next[key] = true;
                    }
                    this.openGroups = next;
                } else {
                    // Top accordion: one parent; keep nested open if it belongs to the active parent.
                    const next = {};
                    if (willOpen || (this.activeGroup && this.activeGroup === key)) {
                        next[key] = true;
                        if (this.activeGroup === key && this.activeNested) {
                            next[this.activeNested] = true;
                        }
                    }
                    this.openGroups = next;
                }
            } else {
                this.openGroups = { ...this.openGroups, [key]: willOpen };
                if (parentKey && willOpen) {
                    this.openGroups[parentKey] = true;
                }
            }
            localStorage.setItem('krushi.nav.groups', JSON.stringify(this.openGroups));
        },

        isGroupOpen(key) {
            if (this.navMode === 'accordion') {
                if (this.activeGroup && key === this.activeGroup) {
                    return true;
                }
                if (this.activeNested && key === this.activeNested) {
                    return true;
                }
            }
            if (this.openGroups[key] === undefined) {
                return false;
            }
            return !!this.openGroups[key];
        },

        get filteredMenus() {
            const q = this.searchQ.trim().toLowerCase();
            if (!q) return this.menuIndex.slice(0, 12);
            return this.menuIndex.filter((m) =>
                (m.label || '').toLowerCase().includes(q) ||
                (m.label_mr || '').toLowerCase().includes(q) ||
                (m.group || '').toLowerCase().includes(q)
            ).slice(0, 20);
        },

        goMenu(url) {
            this.searchOpen = false;
            this.searchQ = '';
            this.navOpen = false;
            window.location.href = url;
        },
    }));

    window.KrushiDrawer = {
        open(url, title, width) {
            Alpine.store('ui').openForm(url, title, width || '32rem');
        },
    };
}
