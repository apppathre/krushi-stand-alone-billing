/**
 * Bulk receipt/payment: tick bills + lump amount → FIFO allocate (full then partial).
 */
(function () {
    function money(n) {
        return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function bind(form) {
        if (!form || form.getAttribute('data-bulk-bound') === '1') return;
        form.setAttribute('data-bulk-bound', '1');

        const lump = form.querySelector('[data-bulk-lump]');
        const pickAll = form.querySelector('[data-bulk-pick-all]');
        const sumEl = form.querySelector('[data-bulk-sum]');
        const fillBtn = form.querySelector('[data-bulk-fill-full]');
        const rows = () => [...form.querySelectorAll('[data-bulk-row]')];

        function selectedRows() {
            return rows().filter((r) => r.querySelector('[data-bulk-bill]')?.checked);
        }

        function paint() {
            let pool = round2(Number(lump?.value || 0));
            let dueTotal = 0;
            let applied = 0;
            let full = 0;
            let partial = 0;

            rows().forEach((row) => {
                const bill = row.querySelector('[data-bulk-bill]');
                const alloc = row.querySelector('[data-bulk-allocate]');
                const status = row.querySelector('[data-bulk-status]');
                const bal = round2(Number(row.getAttribute('data-balance') || 0));
                if (!bill || !alloc) return;

                if (!bill.checked) {
                    alloc.value = '';
                    alloc.disabled = true;
                    if (status) status.textContent = '—';
                    return;
                }

                alloc.disabled = false;
                dueTotal = round2(dueTotal + bal);
                const apply = round2(Math.min(bal, Math.max(0, pool)));
                pool = round2(pool - apply);
                alloc.value = apply > 0 ? apply.toFixed(2) : '';
                applied = round2(applied + apply);

                if (status) {
                    if (apply < 0.01) {
                        status.textContent = 'Skip';
                        status.className = 'text-xs text-ink-soft';
                    } else if (apply + 0.005 >= bal) {
                        status.textContent = 'Full';
                        status.className = 'text-xs font-semibold text-leaf';
                        full += 1;
                    } else {
                        status.textContent = 'Partial';
                        status.className = 'text-xs font-semibold text-accent';
                        partial += 1;
                    }
                }
            });

            if (sumEl) {
                const left = round2(Math.max(0, Number(lump?.value || 0) - applied));
                sumEl.textContent =
                    'Selected due ₹ ' +
                    money(dueTotal) +
                    ' · Allocate ₹ ' +
                    money(applied) +
                    (full || partial ? ' (' + full + ' full, ' + partial + ' partial)' : '') +
                    (left >= 0.01 ? ' · Excess ₹ ' + money(left) + ' (select more bills or lower amount)' : '');
            }
        }

        function round2(n) {
            return Math.round((Number(n) + Number.EPSILON) * 100) / 100;
        }

        form.addEventListener('change', (e) => {
            if (e.target.matches('[data-bulk-bill], [data-bulk-pick-all]')) {
                if (e.target.matches('[data-bulk-pick-all]')) {
                    const on = e.target.checked;
                    form.querySelectorAll('[data-bulk-bill]').forEach((c) => {
                        c.checked = on;
                    });
                }
                paint();
            }
        });

        lump?.addEventListener('input', paint);
        lump?.addEventListener('change', paint);

        fillBtn?.addEventListener('click', () => {
            const due = selectedRows().reduce((s, r) => s + Number(r.getAttribute('data-balance') || 0), 0);
            if (lump) lump.value = round2(due).toFixed(2);
            paint();
        });

        // Allow manual allocate edits without wiping on next lump paint until lump/check changes
        form.addEventListener('input', (e) => {
            if (!e.target.matches('[data-bulk-allocate]')) return;
            const row = e.target.closest('[data-bulk-row]');
            const status = row?.querySelector('[data-bulk-status]');
            const bal = round2(Number(row?.getAttribute('data-balance') || 0));
            let apply = round2(Number(e.target.value || 0));
            if (apply > bal) {
                apply = bal;
                e.target.value = bal.toFixed(2);
            }
            if (status) {
                if (apply < 0.01) {
                    status.textContent = 'Skip';
                    status.className = 'text-xs text-ink-soft';
                } else if (apply + 0.005 >= bal) {
                    status.textContent = 'Full';
                    status.className = 'text-xs font-semibold text-leaf';
                } else {
                    status.textContent = 'Partial';
                    status.className = 'text-xs font-semibold text-accent';
                }
            }
        });

        paint();
    }

    function scan() {
        document.querySelectorAll('form[data-bulk-settle]').forEach(bind);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }
})();
