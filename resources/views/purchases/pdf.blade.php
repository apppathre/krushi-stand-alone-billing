@php
    $lang = $printLang ?? 'en';
    $mr = $lang === 'mr';
    $t = fn (string $en, string $mrLabel) => $mr ? $mrLabel : $en;
    $shop = $mr && filled($company->name_mr) ? $company->name_mr : $company->name;
    $size = $printSize ?? 'a4';
    $supplierName = $mr && filled($purchase->supplier->name_mr) ? $purchase->supplier->name_mr : $purchase->supplier->name;
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <title>{{ $purchase->purchase_no }}</title>
    <style>
        @page { margin: {{ $size === 'quarter' ? '10px 12px' : ($size === 'half' ? '14px 16px' : '24px 28px') }}; }
        @include('partials.pdf-font-css')
        body { font-size: {{ $size === 'quarter' ? '8px' : ($size === 'half' ? '9px' : '10px') }}; color: #1F2A24; }
        h1 { font-size: {{ $size === 'quarter' ? '14px' : ($size === 'half' ? '16px' : '20px') }}; margin: 0; color: #214D39; }
        .muted { color: #5B6A61; }
        .right { text-align: right; }
        .center { text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .title { background: #2F6B4F; color: #fff; padding: 5px 8px; font-size: 12px; font-weight: bold; letter-spacing: 1px; }
        .box { border: 1px solid #CFE0D4; padding: 6px 8px; }
        .items th { background: #E8F0EA; color: #214D39; border: 1px solid #CFE0D4; padding: 4px 5px; font-size: 9px; }
        .items td { border: 1px solid #CFE0D4; padding: 4px 5px; vertical-align: top; }
        .totals td { padding: 3px 6px; }
        .grand td { border-top: 2px solid #2F6B4F; font-size: 12px; font-weight: bold; color: #214D39; }
        .small { font-size: 8.5px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width:62%">
                <h1>{{ $shop }}</h1>
                @if ($company->tagline)<div class="muted">{{ $company->tagline }}</div>@endif
                <div>{{ $company->address }}{{ $company->city ? ', '.$company->city : '' }} {{ $company->pincode }}</div>
                <div>{{ $company->state_name }} ({{ $t('Code', 'कोड') }} {{ $company->state_code }})</div>
                <div>
                    @if ($company->phone){{ $t('Phone', 'फोन') }}: {{ $company->phone }} @endif
                    @if ($company->email) | {{ $company->email }} @endif
                </div>
                <div><strong>GSTIN:</strong> {{ $company->gstin }} @if ($company->pan) | <strong>PAN:</strong> {{ $company->pan }} @endif</div>
            </td>
            <td class="right">
                <div class="title center">{{ $t('PURCHASE BILL', 'खरेदी बील') }}</div>
                <table style="margin-top:6px">
                    <tr><td class="muted">{{ $t('Purchase No', 'खरेदी क्र.') }}</td><td class="right"><strong>{{ $purchase->purchase_no }}</strong></td></tr>
                    <tr><td class="muted">{{ $t('Date', 'दिनांक') }}</td><td class="right">{{ fmt_date($purchase->purchase_date) }}</td></tr>
                    <tr><td class="muted">{{ $t('Place', 'ठिकाण') }}</td><td class="right">{{ $purchase->godown?->display_name }}</td></tr>
                    @if ($purchase->supplier_invoice_no)
                        <tr><td class="muted">{{ $t('Supplier invoice', 'पुरवठादार बील') }}</td><td class="right">{{ $purchase->supplier_invoice_no }}</td></tr>
                    @endif
                    @if ($purchase->supplier_invoice_date)
                        <tr><td class="muted">{{ $t('Supplier date', 'पुरवठादार दिनांक') }}</td><td class="right">{{ fmt_date($purchase->supplier_invoice_date) }}</td></tr>
                    @endif
                    @if ($purchase->status === 'cancelled')
                        <tr><td colspan="2" class="right" style="color:#b91c1c"><strong>{{ $t('CANCELLED', 'रद्द') }}</strong></td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div class="box" style="margin-top:10px">
        <strong>{{ $t('Supplier', 'पुरवठादार') }}:</strong>
        {{ $supplierName }}
        @if ($purchase->supplier->village || $purchase->supplier->city)
            <span class="muted">&mdash; {{ collect([$purchase->supplier->village, $purchase->supplier->city])->filter()->implode(', ') }}</span>
        @endif
        @if ($purchase->supplier->phone)
            <span class="muted">| {{ $t('Ph', 'फोन') }}: {{ $purchase->supplier->phone }}</span>
        @endif
        @if ($purchase->supplier->gstin) <span>| GSTIN: {{ $purchase->supplier->gstin }}</span> @endif
        @if ($purchase->supply_state_code)
            <span class="muted">| {{ $t('Place of supply', 'पुरवठा ठिकाण') }}: {{ \App\Support\IndianStates::name($purchase->supply_state_code) }} ({{ $purchase->supply_state_code }})</span>
        @endif
    </div>

    <table class="items" style="margin-top:10px">
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th>{{ $t('Description', 'वर्णन') }}</th>
                <th>HSN</th>
                <th>{{ $t('Batch / Mfg / Exp', 'बॅच / उत्पाद / वैध') }}</th>
                <th class="right">{{ $t('Qty', 'नग') }}</th>
                <th class="right">{{ $t('Rate', 'दर') }}</th>
                <th class="right">{{ $t('Disc', 'सूट') }}</th>
                <th class="right">{{ $t('Taxable', 'करपात्र') }}</th>
                <th class="right">GST%</th>
                <th class="right">{{ $t('Amount', 'रक्कम') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchase->items as $i => $it)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td><strong>{{ $mr && $it->product->name_mr ? $it->product->name_mr : $it->product->name }}</strong></td>
                    <td>{{ $it->hsn_code }}</td>
                    <td>@if ($it->batch && $it->batch->batch_no !== 'NA')
                        {{ $it->batch->batch_no }}
                        @if ($it->batch->mfg_date) / M {{ $it->batch->mfg_date->format('m-Y') }}@endif
                        @if ($it->batch->expiry_date) / E {{ $it->batch->expiry_date->format('m-Y') }}@endif
                    @endif</td>
                    <td class="right">{{ qty($it->qty) }} {{ $it->product->unit?->short_name }}</td>
                    <td class="right">{{ money($it->rate) }}</td>
                    <td class="right">{{ money($it->discount_amount) }}</td>
                    <td class="right">{{ money($it->taxable_amount) }}</td>
                    <td class="right">{{ qty($it->gst_rate) }}</td>
                    <td class="right">{{ money($it->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top:8px">
        <tr>
            <td style="width:55%; vertical-align:top">
                <div class="box">
                    <strong>{{ $t('Amount in words', 'अक्षरी रक्कम') }}:</strong><br>{{ amount_in_words((float) $purchase->grand_total) }}
                </div>
                @if ($purchase->notes)
                    <div class="box small" style="margin-top:6px">
                        <strong>{{ $t('Notes', 'टिपा') }}:</strong> {{ $purchase->notes }}
                    </div>
                @endif
            </td>
            <td style="vertical-align:top">
                <table class="totals">
                    <tr><td>{{ $t('Subtotal', 'उपयोग') }}</td><td class="right">{{ money($purchase->subtotal) }}</td></tr>
                    @if ($purchase->discount_total > 0)<tr><td>{{ $t('Discount', 'सूट') }}</td><td class="right">- {{ money($purchase->discount_total) }}</td></tr>@endif
                    <tr><td>{{ $t('Taxable value', 'करपात्र मूल्य') }}</td><td class="right">{{ money($purchase->taxable_total) }}</td></tr>
                    @if ($purchase->is_igst)
                        <tr><td>IGST</td><td class="right">{{ money($purchase->igst_total) }}</td></tr>
                    @else
                        <tr><td>CGST</td><td class="right">{{ money($purchase->cgst_total) }}</td></tr>
                        <tr><td>SGST</td><td class="right">{{ money($purchase->sgst_total) }}</td></tr>
                    @endif
                    @if ((float) ($purchase->freight_amount ?? 0) > 0)<tr><td>{{ $t('Freight', 'वाहतूक') }}</td><td class="right">{{ money($purchase->freight_amount) }}</td></tr>@endif
                    @if ((float) ($purchase->hammali_amount ?? 0) > 0)<tr><td>{{ $t('Hammali', 'हमाली') }}</td><td class="right">{{ money($purchase->hammali_amount) }}</td></tr>@endif
                    @if ((float) ($purchase->cash_discount ?? 0) > 0)<tr><td>{{ $t('Cash discount', 'रोख सूट') }}</td><td class="right">- {{ money($purchase->cash_discount) }}</td></tr>@endif
                    <tr><td>{{ $t('Round off', 'पूर्णांक') }}</td><td class="right">{{ money($purchase->round_off) }}</td></tr>
                    <tr class="grand"><td>{{ $t('Grand total', 'एकूण') }}</td><td class="right">Rs. {{ money($purchase->grand_total) }}</td></tr>
                    <tr><td>{{ $t('Paid', 'भरले') }}</td><td class="right">{{ money($purchase->paid_amount) }}</td></tr>
                    <tr><td><strong>{{ $t('Balance due', 'देणे बाकी') }}</strong></td><td class="right"><strong>{{ money($purchase->balance) }}</strong></td></tr>
                    @if ($purchase->due_date)<tr><td class="muted">{{ $t('Due date', 'देय दिनांक') }}</td><td class="right">{{ fmt_date($purchase->due_date) }}@if ($purchase->credit_days) ({{ $purchase->credit_days }} {{ $t('days', 'दिवस') }})@endif</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top:14px">
        <tr>
            <td class="small muted" style="width:60%">
                @php($printTerms = \App\Support\TermsHtml::forPrint($purchase->terms ?? null, $company->invoice_terms))
                @if ($printTerms)
                    <strong>{{ $t('Terms', 'अटी') }}:</strong>
                    <div class="terms-html">{!! $printTerms !!}</div>
                @endif
            </td>
            <td class="right">
                <br><br>{{ $t('For', 'करीता') }} <strong>{{ $shop }}</strong><br><br>
                <span class="small muted">{{ $t('Authorised signatory', 'अधिकृत सही') }}</span>
            </td>
        </tr>
    </table>
</body>
</html>
