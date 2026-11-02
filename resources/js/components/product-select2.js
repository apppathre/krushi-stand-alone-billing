/**
 * Searchable selects (Select2) — loaded only when a page actually needs them.
 * Do NOT statically import jquery/select2/css here: that pulled Select2 CSS into
 * every screen (dashboard included) and distorted layout.
 */

const NS = '.krushiSearchable';
const RESULT_LIMIT = 40;

let select2Ready = null;

/** Lazy-load jQuery + Select2 + CSS once per session. */
export function ensureSelect2() {
    if (select2Ready) {
        return select2Ready;
    }

    select2Ready = Promise.all([
        import('jquery'),
        import('select2'),
        import('select2/dist/css/select2.min.css'),
        import('../../css/select2-krushi.css'),
    ]).then(([jqMod, s2Mod]) => {
        const $ = jqMod.default;
        window.$ = window.jQuery = $;

        const factory = s2Mod.default;
        if (typeof factory === 'function' && (!$.fn || !$.fn.select2)) {
            factory(window, $);
        }
        if (!$.fn?.select2) {
            console.error('Select2 failed to load on jQuery.');
        }

        document.body?.classList.add('has-select2');

        return $;
    }).catch((err) => {
        select2Ready = null;
        console.error('Select2 load failed', err);
        throw err;
    });

    return select2Ready;
}

function select2Base(placeholder, allowClear) {
    return {
        width: '100%',
        placeholder: placeholder || 'Select',
        allowClear: !!allowClear,
        minimumResultsForSearch: 0,
        dropdownParent: window.jQuery(document.body),
    };
}

function destroySelect2(el) {
    const $ = window.jQuery;
    if (!$ || !el) return;
    const $el = $(el);
    $el.off(NS);
    if ($.fn?.select2 && $el.hasClass('select2-hidden-accessible')) {
        try {
            $el.select2('destroy');
        } catch (e) {
            // ignore destroy races
        }
    }
}

export function productStockLabel(product, placeId = null, { showStock = true } = {}) {
    if (!product) return '';
    const code = product.code ? ` [${product.code}]` : '';
    if (!showStock || product.stock == null) {
        return `${product.name}${code}`.trim();
    }
    const unit = product.unit || '';
    let qty = Number(product.stock ?? 0);
    if (placeId && Array.isArray(product.stock_by_place) && product.stock_by_place.length) {
        const row = product.stock_by_place.find((s) => String(s.godown_id) === String(placeId));
        if (row) qty = Number(row.qty ?? 0);
    }
    const qtyText = Number.isFinite(qty)
        ? qty.toLocaleString(undefined, { maximumFractionDigits: 3 })
        : '0';

    return `${product.name}${code} · ${qtyText}${unit ? ` ${unit}` : ''}`.trim();
}

function filterProducts(products, term, showStock) {
    const q = String(term || '').toLowerCase().trim();
    let list = products || [];
    if (q) {
        list = list.filter((p) => (
            String(p.name || '').toLowerCase().includes(q)
            || String(p.code || '').toLowerCase().includes(q)
            || String(p.hsn || '').toLowerCase().includes(q)
        ));
    } else if (showStock) {
        list = list.slice().sort((a, b) => Number(b.stock || 0) - Number(a.stock || 0));
    }
    return list;
}

function setSelectedProductOption(el, products, value, placeId, placeholder, showStock) {
    const current = value ? String(value) : '';
    el.innerHTML = '';
    el.appendChild(new Option(placeholder, '', !current, !current));
    if (!current) return;
    const p = (products || []).find((row) => String(row.id) === current);
    const label = p
        ? productStockLabel(p, placeId, { showStock })
        : current;
    el.appendChild(new Option(label, current, true, true));
}

/**
 * Product Select2 — shared catalog in memory, max RESULT_LIMIT rows in the menu.
 */
export function bindProductSelect2(el, opts = {}) {
    if (!el) return Promise.resolve();

    return ensureSelect2().then(($) => {
        const {
            products = [],
            value = '',
            placeId = null,
            placeholder = 'Select product',
            showStock = true,
            allowClear = true,
            onChange = null,
        } = opts;

        const sig = `${showStock ? 1 : 0}|${placeId || ''}|${products.length}`;
        const $el = $(el);

        if (
            $.fn?.select2
            && $el.hasClass('select2-hidden-accessible')
            && el.dataset.krushiProdSig === sig
        ) {
            setSelectedProductOption(el, products, value, placeId, placeholder, showStock);
            $el.off(NS);
            $el.on(`change${NS}`, function () {
                if (typeof onChange === 'function') onChange($(this).val() || '');
            });
            if (String($el.val() || '') !== String(value || '')) {
                $el.val(value ? String(value) : null).trigger('change.select2');
            }
            return;
        }

        destroySelect2(el);
        setSelectedProductOption(el, products, value, placeId, placeholder, showStock);
        el.dataset.krushiProdSig = sig;

        if (!$.fn?.select2) {
            $el.off(NS);
            $el.on(`change${NS}`, function () {
                if (typeof onChange === 'function') onChange($(this).val() || '');
            });
            return;
        }

        $el.select2({
            ...select2Base(placeholder, allowClear),
            ajax: {
                delay: 60,
                transport(params, success) {
                    const term = params?.data?.q || '';
                    const rows = filterProducts(products, term, showStock)
                        .slice(0, RESULT_LIMIT)
                        .map((p) => ({
                            id: String(p.id),
                            text: productStockLabel(p, placeId, { showStock }),
                        }));
                    success({ results: rows });
                },
                processResults: (data) => data,
            },
        });

        $el.val(value ? String(value) : null).trigger('change.select2');
        $el.off(NS);
        $el.on(`change${NS}`, function () {
            if (typeof onChange === 'function') onChange($(this).val() || '');
        });
    }).catch(() => {
        // Fallback: keep native select usable if Select2 fails to load.
    });
}

export function unbindProductSelect2(el) {
    if (!el) return;
    delete el.dataset.krushiProdSig;
    destroySelect2(el);
}

/** Customer / supplier / ledger selects — keep native options; body-attached menu. */
export function enhanceSelect(el, opts = {}) {
    if (!el) return Promise.resolve();

    return ensureSelect2().then(($) => {
        const {
            placeholder = null,
            allowClear = true,
            onChange = null,
        } = opts;

        const ph = placeholder
            || el.getAttribute('data-placeholder')
            || el.querySelector('option[value=""]')?.textContent
            || 'Select';

        const current = el.value;
        destroySelect2(el);
        const $el = $(el);

        if (!$.fn?.select2) {
            $el.off(NS);
            $el.on(`change${NS}`, function () {
                if (typeof onChange === 'function') onChange($(this).val() || '');
            });
            return;
        }

        $el.select2({
            ...select2Base(ph, allowClear),
            matcher(params, data) {
                if ($.trim(params.term || '') === '') return data;
                if (typeof data.text === 'undefined') return null;
                if (!data.id) return data;
                const term = String(params.term).toLowerCase();
                return String(data.text).toLowerCase().includes(term) ? data : null;
            },
        });

        // Do not .trigger('change') on init — HTML already has the selected option, and
        // a change event re-submits filter forms (bulk receipt ?party_id= refresh loop).
        if (current && String($el.val() || '') !== String(current)) {
            $el.val(String(current));
        }
        $el.off(NS);
        $el.on(`change${NS}`, function () {
            if (typeof onChange === 'function') onChange($(this).val() || '');
        });

        // Filter GET forms: submit only on real user pick/clear, never on Select2 init.
        if (el.dataset.filterSubmit === '1' && el.form) {
            $el.off('select2:select.krushiFilter select2:clear.krushiFilter');
            $el.on('select2:select.krushiFilter select2:clear.krushiFilter', () => {
                el.form.submit();
            });
        }
    }).catch(() => {});
}

export function refreshEnhancedSelect(el, value = null) {
    if (!el) return Promise.resolve();
    return ensureSelect2().then(($) => {
        const $el = $(el);
        if ($.fn?.select2 && $el.hasClass('select2-hidden-accessible')) {
            if (value !== null && value !== undefined && value !== '') {
                $el.val(String(value)).trigger('change');
            } else {
                $el.trigger('change.select2');
            }
            return;
        }
        return enhanceSelect(el, { allowClear: !el.required, onChange: null }).then(() => {
            if (value !== null && value !== undefined && value !== '') {
                $(el).val(String(value)).trigger('change');
            }
        });
    }).catch(() => {});
}

export function autoEnhanceSearchable(root = document) {
    const nodes = root.querySelectorAll('select.js-searchable');
    if (!nodes.length) return;

    const needed = [...nodes].filter((el) => !(
        el.classList.contains('js-party-select')
        || el.classList.contains('js-sale-product')
        || el.classList.contains('js-txn-product')
    ));
    if (!needed.length) return;

    ensureSelect2().then(() => {
        needed.forEach((el) => {
            if (el.dataset.searchableReady === '1' && window.jQuery?.(el).hasClass('select2-hidden-accessible')) {
                return;
            }
            enhanceSelect(el, { allowClear: !el.required, onChange: null });
            el.dataset.searchableReady = '1';
        });
    }).catch(() => {});
}

document.addEventListener('DOMContentLoaded', () => {
    // Only touch the network/CSS when this page actually has a searchable select.
    if (document.querySelector('select.js-searchable, select.js-party-select, select.js-txn-product, select.js-sale-product')) {
        autoEnhanceSearchable(document);
    }
});
document.addEventListener('krushi:drawer-loaded', (e) => {
    const root = e.detail?.root || document.getElementById('ui-drawer-body') || document;
    if (root.querySelector?.('select.js-searchable, select.js-txn-product')) {
        autoEnhanceSearchable(root);
    }
});
