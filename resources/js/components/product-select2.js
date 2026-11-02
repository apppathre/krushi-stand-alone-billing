import $ from 'jquery';
import 'select2';
import 'select2/dist/css/select2.min.css';

window.$ = window.jQuery = $;

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

function destroySelect2(el) {
    const $el = $(el);
    if ($el.hasClass('select2-hidden-accessible')) {
        $el.off('.krushiProduct');
        $el.select2('destroy');
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

    const $el = $(el);
    $el.empty();
    $el.append(new Option(placeholder, '', !value, !value));

    products.forEach((p) => {
        const selected = String(p.id) === String(value);
        const opt = new Option(productStockLabel(p, placeId), String(p.id), selected, selected);
        opt.dataset.stock = String(p.stock ?? 0);
        $el.append(opt);
    });

    $el.select2({
        width: '100%',
        placeholder,
        allowClear: true,
        dropdownAutoWidth: false,
        matcher(params, data) {
            if ($.trim(params.term || '') === '') return data;
            if (!data.id || !data.text) return null;
            const term = params.term.toLowerCase();
            return data.text.toLowerCase().includes(term) ? data : null;
        },
    });

    if (value) {
        $el.val(String(value)).trigger('change.select2');
    } else {
        $el.val(null).trigger('change.select2');
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
