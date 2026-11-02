import $ from 'jquery';
import select2Factory from 'select2';
import 'select2/dist/css/select2.min.css';

// Vite/ESM: select2 exports a factory — must bind it to this jQuery instance.
window.$ = window.jQuery = $;
if (typeof select2Factory === 'function' && (!$.fn || !$.fn.select2)) {
    select2Factory(window, $);
}
if (!$.fn?.select2) {
    console.error('Select2 failed to load on jQuery — searchable selects will fall back to native.');
}

const NS = '.krushiSearchable';
const RESULT_LIMIT = 40;

/**
 * Always attach dropdown to <body> so it is not clipped / offset by overflow parents
 * (.card, .txn-line, sticky footers, etc.).
 */
function select2Base(placeholder, allowClear) {
    return {
        width: '100%',
        placeholder: placeholder || 'Select',
        allowClear: !!allowClear,
        minimumResultsForSearch: 0,
        dropdownParent: $(document.body),
        dropdownCssClass: 'krushi-select2-drop',
        selectionCssClass: 'krushi-select2-sel',
    };
}

function destroySelect2(el) {
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

/**
 * Format product option text. Sale lines can include place stock.
 */
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
        // Prefer in-stock items at the top when browsing without a query.
        list = list.slice().sort((a, b) => Number(b.stock || 0) - Number(a.stock || 0));
    }
    return list;
}

/**
 * Product Select2: does NOT clone the full catalog into every line (memory-safe).
 * Search filters a shared array and returns at most RESULT_LIMIT rows.
 */
export function bindProductSelect2(el, {
    products = [],
    value = '',
    placeId = null,
    placeholder = 'Select product',
    showStock = true,
    allowClear = true,
    onChange = null,
} = {}) {
    if (!el) return;

    // Avoid full remount if already bound to the same catalog reference + place.
    const sig = `${showStock ? 1 : 0}|${placeId || ''}|${products.length}`;
    const $el = $(el);
    if (
        $.fn?.select2
        && $el.hasClass('select2-hidden-accessible')
        && el.dataset.krushiProdSig === sig
    ) {
        // Only sync selected value / label.
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
        // Native fallback: still avoid dumping the whole catalog — keep selected only + note.
        $el.off(NS);
        $el.on(`change${NS}`, function () {
            if (typeof onChange === 'function') onChange($(this).val() || '');
        });
        return;
    }

    try {
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

        if (value) {
            $el.val(String(value)).trigger('change.select2');
        } else {
            $el.val(null).trigger('change.select2');
        }
    } catch (e) {
        console.error('Select2 product init failed', e);
    }

    $el.off(NS);
    $el.on(`change${NS}`, function () {
        if (typeof onChange === 'function') onChange($(this).val() || '');
    });
}

/** Keep only placeholder + current selection in the <select> DOM (light). */
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

export function unbindProductSelect2(el) {
    if (!el) return;
    delete el.dataset.krushiProdSig;
    destroySelect2(el);
}

/**
 * Enhance an existing <select> (customer / supplier / filters) with Select2 search.
 * Uses body dropdown parent so the menu lines up under the field.
 */
export function enhanceSelect(el, {
    placeholder = null,
    allowClear = true,
    onChange = null,
} = {}) {
    if (!el) return;

    const ph = placeholder
        || el.getAttribute('data-placeholder')
        || el.querySelector('option[value=""]')?.textContent
        || 'Select';

    const current = el.value;
    destroySelect2(el);

    const $el = $(el);
    if (!$.fn?.select2) {
        $el.on(`change${NS}`, function () {
            if (typeof onChange === 'function') onChange($(this).val() || '');
        });
        return;
    }

    try {
        // Keep native <option>s (one party/ledger list per page is fine).
        // Product lines use bindProductSelect2 AJAX path for memory.
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

        if (current) {
            $el.val(String(current)).trigger('change.select2');
        } else {
            $el.val(null).trigger('change.select2');
        }
    } catch (e) {
        console.error('Select2 enhance failed', e);
    }

    $el.off(NS);
    $el.on(`change${NS}`, function () {
        if (typeof onChange === 'function') onChange($(this).val() || '');
    });
}

/** After manually adding/updating an <option>, refresh Select2 display. */
export function refreshEnhancedSelect(el, value = null) {
    if (!el) return;
    const $el = $(el);
    if ($.fn?.select2 && $el.hasClass('select2-hidden-accessible')) {
        if (value !== null && value !== undefined && value !== '') {
            $el.val(String(value)).trigger('change');
        } else {
            $el.trigger('change.select2');
        }
        return;
    }
    enhanceSelect(el, {
        onChange: null,
        allowClear: !el.required,
    });
    if (value !== null && value !== undefined && value !== '') {
        $el.val(String(value)).trigger('change');
    }
}

/**
 * Auto-enhance marks: class "js-searchable" (and optional data-placeholder).
 * Skips product line selects (those use bindProductSelect2 via Alpine).
 */
export function autoEnhanceSearchable(root = document) {
    root.querySelectorAll('select.js-searchable').forEach((el) => {
        if (el.classList.contains('js-party-select')
            || el.classList.contains('js-sale-product')
            || el.classList.contains('js-txn-product')) {
            return;
        }
        if (el.dataset.searchableReady === '1' && $(el).hasClass('select2-hidden-accessible')) return;
        enhanceSelect(el, {
            allowClear: !el.required,
            onChange: null,
        });
        el.dataset.searchableReady = '1';
    });
}

document.addEventListener('DOMContentLoaded', () => autoEnhanceSearchable(document));
document.addEventListener('krushi:drawer-loaded', (e) => {
    const root = e.detail?.root || document.getElementById('ui-drawer-body') || document;
    autoEnhanceSearchable(root);
});
