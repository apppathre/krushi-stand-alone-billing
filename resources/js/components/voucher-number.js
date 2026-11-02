/**
 * Transaction Master number series: pick format, AJAX-preview next invoice no.
 * Preview must not increment. Save still allocates via InvoiceNumberGenerator::next.
 */
(function (global) {
    function bind(root) {
        if (!root || root.getAttribute('data-vn-bound') === '1') return;
        const url = root.getAttribute('data-next-number-url');
        const type = root.getAttribute('data-voucher-type');
        const numberEl = root.querySelector('[data-voucher-number]');
        const formatEl = root.querySelector('[data-voucher-format]');
        const hintEl = root.querySelector('[data-voucher-number-hint]');
        const dateSel = root.getAttribute('data-date-input') || '[name=invoice_date]';
        const form = root.closest('form');
        const dateEl = (form && form.querySelector(dateSel)) || document.querySelector(dateSel);
        if (!url || !type || !numberEl) return;

        root.setAttribute('data-vn-bound', '1');
        let seq = 0;

        function setHint(text) {
            if (hintEl) hintEl.textContent = text || '';
        }

        function paintFormats(formats, selectedId) {
            if (!formatEl || !Array.isArray(formats)) return;
            const keep = selectedId != null ? String(selectedId) : String(formatEl.value || '');
            formatEl.innerHTML = '';
            formats.forEach((f) => {
                const opt = document.createElement('option');
                opt.value = String(f.id);
                opt.textContent = f.name + (f.prefix ? ' (' + f.prefix + ')' : '');
                if (String(f.id) === keep || (!keep && f.is_default)) opt.selected = true;
                formatEl.appendChild(opt);
            });
            if (!formatEl.value && formats[0]) formatEl.value = String(formats[0].id);
        }

        function load() {
            const n = ++seq;
            const params = new URLSearchParams({ type });
            if (dateEl && dateEl.value) params.set('date', dateEl.value);
            if (formatEl && formatEl.value) params.set('format_id', formatEl.value);
            numberEl.value = '…';
            return fetch(url + '?' + params.toString(), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then((res) => res.json().then((body) => ({ ok: res.ok, body })))
                .then((res) => {
                    if (n !== seq) return false;
                    if (res.ok && res.body && res.body.number) {
                        paintFormats(res.body.formats, res.body.format_id);
                        numberEl.value = res.body.number;
                        setHint('Preview only. Number is reserved when you save.');
                        return true;
                    }
                    numberEl.value = '';
                    setHint((res.body && res.body.message) || 'Pick a series that covers this date.');
                    return false;
                })
                .catch(() => {
                    if (n !== seq) return false;
                    numberEl.value = '';
                    setHint('Could not load the next number.');
                    return false;
                });
        }

        root._vnLoad = load;
        if (formatEl) formatEl.addEventListener('change', load);
        if (dateEl) dateEl.addEventListener('change', load);
        load();
    }

    function scan(scope) {
        (scope || document).querySelectorAll('[data-voucher-number-root]').forEach(bind);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => scan());
    } else {
        scan();
    }

    document.addEventListener('krushi:drawer-loaded', (e) => scan(e.target || document));

    global.KrushiVoucherNumber = { bind, scan };
})(window);
