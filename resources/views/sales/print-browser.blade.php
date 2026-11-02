@php
    $lang = $printLang ?? 'en';
    $mr = $lang === 'mr';
    $t = fn (string $en, string $mrLabel) => $mr ? $mrLabel : $en;
    $shop = $mr && filled($company->name_mr) ? $company->name_mr : $company->name;
    $pdfOk = (bool) ($pdfFontOk ?? false);
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sale->invoice_no }} — {{ $t('Print', 'प्रिंट') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;600;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --ink:#1F2A24; --leaf:#214D39; --line:#CFE0D4; --soft:#5B6A61; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            font-family: "Noto Sans Devanagari", "Noto Sans", system-ui, sans-serif;
            background: #eef4ef;
        }
        .toolbar {
            position: sticky; top: 0; z-index: 5;
            display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; justify-content: space-between;
            padding: .75rem 1rem; background: #fff; border-bottom: 1px solid var(--line);
        }
        .toolbar h1 { margin: 0; font-size: 1rem; color: var(--leaf); }
        .toolbar p { margin: .15rem 0 0; font-size: .8rem; color: var(--soft); }
        .btns { display: flex; flex-wrap: wrap; gap: .4rem; }
        .btn {
            appearance: none; border: 1px solid var(--line); background: #fff; color: var(--ink);
            border-radius: 10px; padding: .45rem .85rem; font: inherit; font-weight: 650; cursor: pointer; text-decoration: none;
        }
        .btn-primary { background: #2F6B4F; border-color: #2F6B4F; color: #fff; }
        .btn-warn { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }
        .sheet {
            max-width: 900px; margin: 1rem auto 2rem; background: #fff;
            border: 1px solid var(--line); border-radius: 12px; padding: 1.25rem 1.4rem;
            box-shadow: 0 10px 30px rgba(31,107,74,.06);
        }
        .head { display: flex; justify-content: space-between; gap: 1rem; }
        .shop { font-size: 1.35rem; font-weight: 700; color: var(--leaf); margin: 0; }
        .muted { color: var(--soft); font-size: .85rem; }
        .title {
            background: #2F6B4F; color: #fff; padding: .35rem .6rem; font-weight: 700;
            letter-spacing: .04em; text-align: center; border-radius: 6px;
        }
        .box { border: 1px solid var(--line); border-radius: 8px; padding: .65rem .75rem; margin-top: .85rem; }
        table { width: 100%; border-collapse: collapse; }
        .items { margin-top: .85rem; }
        .items th { background: #E8F0EA; color: var(--leaf); border: 1px solid var(--line); padding: .4rem .45rem; font-size: .75rem; text-align: left; }
        .items td { border: 1px solid var(--line); padding: .4rem .45rem; vertical-align: top; font-size: .88rem; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .grand { font-size: 1.05rem; font-weight: 700; color: var(--leaf); }
        .warn {
            margin: .75rem 1rem 0; padding: .7rem .9rem; border-radius: 10px;
            background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; font-size: .88rem;
        }
        @media print {
            body { background: #fff; }
            .toolbar, .warn, .no-print { display: none !important; }
            .sheet { margin: 0; border: 0; box-shadow: none; border-radius: 0; max-width: none; padding: 0; }
        }
    </style>
</head>
<body>
<div class="toolbar no-print">
    <div>
        <h1>{{ $t('Browser print', 'ब्राउझर प्रिंट') }} · {{ $sale->invoice_no }}</h1>
        <p>
            {{ $mr ? __('Marathi labels with web fonts (best for Devanagari)') : __('English labels') }}
            @if ($mr && ! $pdfOk)
                · {{ __('PDF Marathi font not installed on server — use this page or English PDF') }}
            @elseif ($mr && $pdfOk)
                · {{ __('Marathi PDF font is also available') }}
            @endif
        </p>
    </div>
    <div class="btns">
        <button type="button" class="btn btn-primary" onclick="window.print()">{{ __('Print') }}</button>
        <a class="btn" href="{{ route('sales.pdf', [$sale, 'format' => $printFormat, 'size' => $printSize, 'lang' => $lang]) }}" target="_blank">{{ __('Open PDF') }}</a>
        @if ($lang === 'mr')
            <a class="btn" href="{{ route('sales.pdf', [$sale, 'format' => $printFormat, 'size' => $printSize, 'lang' => 'en']) }}" target="_blank">{{ __('English PDF') }}</a>
        @endif
        <a class="btn" href="{{ route('sales.show', $sale) }}">{{ __('Back') }}</a>
    </div>
</div>

@if ($mr && ! $pdfOk)
    <div class="warn no-print">
        {{ __('Marathi PDF needs the Noto Sans Devanagari font on the server. Browser print below is the recommended option for clear Marathi text.') }}
    </div>
@endif

<div class="sheet">
    <div class="head">
        <div>
            <p class="shop">{{ $shop }}</p>
            @if ($company->tagline)<div class="muted">{{ $company->tagline }}</div>@endif
            <div>{{ $company->address }}{{ $company->city ? ', '.$company->city : '' }} {{ $company->pincode }}</div>
            <div class="muted">
                @if ($company->phone){{ $t('Phone', 'फोन') }}: {{ $company->phone }}@endif
                @if ($company->gstin) · GSTIN: {{ $company->gstin }}@endif
            </div>
        </div>
        <div style="min-width:11rem">
            <div class="title">{{ $t('TAX INVOICE', 'कर बील') }}</div>
            <table style="margin-top:.45rem;font-size:.9rem">
                <tr><td class="muted">{{ $t('Invoice No', 'बील क्र.') }}</td><td class="num"><strong>{{ $sale->invoice_no }}</strong></td></tr>
                <tr><td class="muted">{{ $t('Date', 'दिनांक') }}</td><td class="num">{{ fmt_date($sale->invoice_date) }}</td></tr>
            </table>
        </div>
    </div>

    <div class="box">
        <strong>{{ $t('Bill to', 'ग्राहक') }}:</strong>
        {{ $sale->customer_name ?: ($mr && $sale->client?->name_mr ? $sale->client->name_mr : $sale->client?->name) }}
        @if ($sale->customer_phone ?: $sale->client?->phone)
            <span class="muted"> · {{ $t('Ph', 'फोन') }}: {{ $sale->customer_phone ?: $sale->client?->phone }}</span>
        @endif
    </div>

    <table class="items">
        <thead>
        <tr>
            <th>#</th>
            <th>{{ $t('Description', 'वर्णन') }}</th>
            <th>HSN</th>
            <th class="num">{{ $t('Qty', 'नग') }}</th>
            <th class="num">{{ $t('Rate', 'दर') }}</th>
            <th class="num">{{ $t('Amount', 'रक्कम') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($sale->items as $i => $it)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    {{ $mr && filled($it->product?->name_mr) ? $it->product->name_mr : $it->product?->name }}
                    @if ($it->product?->packing)<div class="muted">{{ $it->product->packing }}</div>@endif
                </td>
                <td>{{ $it->hsn_code }}</td>
                <td class="num">{{ qty($it->qty) }} {{ $it->product?->unit?->short_name }}</td>
                <td class="num">{{ money($it->rate) }}</td>
                <td class="num">{{ money($it->line_total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table style="margin-top:1rem;max-width:16rem;margin-left:auto;font-size:.95rem">
        <tr><td class="muted">{{ $t('Taxable', 'करपात्र') }}</td><td class="num">{{ money($sale->taxable_total) }}</td></tr>
        @if ((float) $sale->cgst_total > 0)
            <tr><td class="muted">CGST</td><td class="num">{{ money($sale->cgst_total) }}</td></tr>
            <tr><td class="muted">SGST</td><td class="num">{{ money($sale->sgst_total) }}</td></tr>
        @endif
        @if ((float) $sale->igst_total > 0)
            <tr><td class="muted">IGST</td><td class="num">{{ money($sale->igst_total) }}</td></tr>
        @endif
        <tr class="grand"><td>{{ $t('Grand total', 'एकूण') }}</td><td class="num">₹ {{ money($sale->grand_total) }}</td></tr>
    </table>
</div>

<script>
    // Auto-open print dialog when requested (?autoprint=1)
    @if (request()->boolean('autoprint'))
    window.addEventListener('load', () => setTimeout(() => window.print(), 350));
    @endif
</script>
</body>
</html>
