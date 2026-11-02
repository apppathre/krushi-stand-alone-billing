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

/** Shared Select2 options — search always visible (mobile-friendly). */
function select2Options(el, placeholder, allowClear) {
    const parent = el.closest('.txn-line, .txn-desk, .card-body, form, .ui-drawer-body') || document.body;

    return {
        width: '100%',
        placeholder: placeholder || 'Select',
        allowClear: !!allowClear,
        minimumResultsForSearch: 0,
        dropdownParent: $(parent),
        matcher(params, data) {
            if ($.trim(params.term || '') === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (!data.id) return data;
            const term = String(params.term).toLowerCase();
            return String(data.text).toLowerCase().includes(term) ? data : null;
        },
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

function fillProductOptions(el, products, value, placeId, placeholder, showStock) {
    el.innerHTML = '';
    el.appendChild(new Option(placeholder, '', !value, !value));
    products.forEach((p) => {
        const selected = String(p.id) === String(value);
        el.appendChild(new Option(
            productStockLabel(p, placeId, { showStock }),
            String(p.id),
            selected,
            selected,
        ));
    });
}

/**
 * Rebuild options from a product list + Select2 (sale / purchase / PO / journal lines).
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

    destroySelect2(el);
    fillProductOptions(el, products, value, placeId, placeholder, showStock);

    const $el = $(el);
    if (!$.fn?.select2) {
        $el.on(`change${NS}`, function () {
            if (typeof onChange === 'function') onChange($(this).val() || '');
        });
        return;
    }

    try {
        $el.select2(select2Options(el, placeholder, allowClear));
        $el.val(value ? String(value) : null).trigger('change.select2');
    } catch (e) {
        console.error('Select2 product init failed', e);
    }

    $el.on(`change${NS}`, function () {
        if (typeof onChange === 'function') onChange($(this).val() || '');
    });
}

export function unbindProductSelect2(el) {
    if (!el) return;
    destroySelect2(el);
}

/**
 * Enhance an existing <select> (customer / supplier / filters) with Select2 search.
 * Keeps current options; safe to call again after options are added.
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
        $el.select2(select2Options(el, ph, allowClear));
        if (current) {
            $el.val(String(current)).trigger('change.select2');
        } else {
            $el.val(null).trigger('change.select2');
        }
    } catch (e) {
        console.error('Select2 enhance failed', e);
    }

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
        // Alpine-owned picks (invoice / journal) mount themselves with onChange hooks.
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
