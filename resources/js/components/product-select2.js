import $ from 'jquery';
import select2Factory from 'select2';
import 'select2/dist/css/select2.min.css';

// Vite/ESM: select2 exports a factory — must bind it to this jQuery instance.
window.$ = window.jQuery = $;
if (typeof select2Factory === 'function' && (!$.fn || !$.fn.select2)) {
    select2Factory(window, $);
}
if (!$.fn?.select2) {
    console.error('Select2 failed to load on jQuery — product search will use native select.');
}

/**
 * Format product option text with current stock qty (place-aware when possible).
 */
export function productStockLabel(product, placeId = null) {
    if (!product) return '';
    const code = product.code ? ` [${product.code}]` : '';
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

function fillNativeOptions(el, products, value, placeId, placeholder) {
    el.innerHTML = '';
    const empty = new Option(placeholder, '', !value, !value);
    el.appendChild(empty);
    products.forEach((p) => {
        const selected = String(p.id) === String(value);
        el.appendChild(new Option(productStockLabel(p, placeId), String(p.id), selected, selected));
    });
}

function destroySelect2(el) {
    const $el = $(el);
    if ($.fn?.select2 && $el.hasClass('select2-hidden-accessible')) {
        $el.off('.krushiProduct');
        try {
            $el.select2('destroy');
        } catch (e) {
            // ignore destroy races
        }
    }
}

/**
 * Bind a sale-line product <select> to Select2. Keeps native name= so form save works.
 * Rebuilds options from Alpine products list so stock text stays current.
 */
export function bindProductSelect2(el, {
    products = [],
    value = '',
    placeId = null,
    placeholder = 'Select product',
    onChange = null,
} = {}) {
    if (!el) return;

    destroySelect2(el);
    fillNativeOptions(el, products, value, placeId, placeholder);

    const $el = $(el);
    $el.off('.krushiProduct');

    if (!$.fn?.select2) {
        // Fallback: native select still has stock labels and posts product_id.
        $el.on('change.krushiProduct', function () {
            if (typeof onChange === 'function') onChange($(this).val() || '');
        });
        return;
    }

    try {
        $el.select2({
            width: '100%',
            placeholder,
            allowClear: true,
            dropdownParent: $(el.closest('.txn-line') || el.parentElement || document.body),
            matcher(params, data) {
                if ($.trim(params.term || '') === '') return data;
                if (typeof data.text === 'undefined') return null;
                // Keep placeholder / optgroups searchable path stable.
                if (!data.id) return data;
                const term = String(params.term).toLowerCase();
                return String(data.text).toLowerCase().includes(term) ? data : null;
            },
        });

        if (value) {
            $el.val(String(value)).trigger('change.select2');
        } else {
            $el.val(null).trigger('change.select2');
        }
    } catch (e) {
        console.error('Select2 init failed', e);
    }

    $el.on('change.krushiProduct', function () {
        if (typeof onChange === 'function') {
            onChange($(this).val() || '');
        }
    });
}

export function unbindProductSelect2(el) {
    if (!el) return;
    destroySelect2(el);
}
