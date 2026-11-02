@php
    $lang = $printLang ?? 'en';
    $mr = $lang === 'mr';
    $t = fn (string $en, string $mrLabel) => $mr ? $mrLabel : $en;
    $shop = $mr && filled($company->name_mr) ? $company->name_mr : $company->name;
    $size = $printSize ?? 'a4';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->invoice_no }}</title>
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
                <div class="small muted">
                    @if ($company->seed_licence_no) {{ $t('Seed Lic', 'बियाणे परवाना') }}: {{ $company->seed_licence_no }} @endif
                    @if ($company->fertilizer_licence_no) | {{ $t('Fertilizer Lic', 'खत परवाना') }}: {{ $company->fertilizer_licence_no }} @endif
                    @if ($company->pesticide_licence_no) | {{ $t('Pesticide Lic', 'कीटकनाशक परवाना') }}: {{ $company->pesticide_licence_no }} @endif
                </div>
            </td>
            <td class="right">
                <div class="title center">{{ $t('TAX INVOICE', 'कर बील') }}</div>
                <table style="margin-top:6px">
                    <tr><td class="muted">{{ $t('Invoice No', 'बील क्र.') }}</td><td class="right"><strong>{{ $sale->invoice_no }}</strong></td></tr>
                    <tr><td class="muted">{{ $t('Date', 'दिनांक') }}</td><td class="right">{{ fmt_date($sale->invoice_date) }}</td></tr>
                    <tr><td class="muted">{{ $t('Place of supply', 'पुरवठा ठिकाण') }}</td><td class="right">{{ \App\Support\IndianStates::name($sale->supply_state_code) }} ({{ $sale->supply_state_code }})</td></tr>
                    @if ($sale->status === 'cancelled')<tr><td colspan="2" class="right" style="color:#b91c1c"><strong>{{ $t('CANCELLED', 'रद्द') }}</strong></td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    <div class="box" style="margin-top:10px">
        <strong>{{ $t('Bill to', 'ग्राहक') }}:</strong>
        {{ $sale->customer_name ?: ($mr && $sale->client->name_mr ? $sale->client->name_mr : $sale->client->name) }}
        @if ($sale->client->village || $sale->client->city)
            <span class="muted">&mdash; {{ collect([$sale->client->village, $sale->client->city])->filter()->implode(', ') }}</span>
        @endif
        @if ($sale->customer_phone ?: $sale->client->phone)
            <span class="muted">| {{ $t('Ph', 'फोन') }}: {{ $sale->customer_phone ?: $sale->client->phone }}</span>
        @endif
        @if ($sale->client->gstin) <span>| GSTIN: {{ $sale->client->gstin }}</span> @endif
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
            @foreach ($sale->items as $i => $it)
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
                    <strong>{{ $t('Amount in words', 'अक्षरी रक्कम') }}:</strong><br>{{ amount_in_words((float) $sale->grand_total) }}
                </div>
                @if ($company->bank_name)
                    <div class="box small" style="margin-top:6px">
                        <strong>{{ $t('Bank', 'बँक') }}:</strong> {{ $company->bank_name }}
                        | A/c {{ $company->bank_account_no }}
                        | IFSC {{ $company->bank_ifsc }}
                        @if ($company->bank_branch) | {{ $company->bank_branch }} @endif
                    </div>
                @endif
            </td>
            <td style="vertical-align:top">
                <table class="totals">
                    <tr><td>{{ $t('Subtotal', 'उपयोग') }}</td><td class="right">{{ money($sale->subtotal) }}</td></tr>
                    @if ($sale->discount_total > 0)<tr><td>{{ $t('Discount', 'सूट') }}</td><td class="right">- {{ money($sale->discount_total) }}</td></tr>@endif
                    <tr><td>{{ $t('Taxable value', 'करपात्र मूल्य') }}</td><td class="right">{{ money($sale->taxable_total) }}</td></tr>
                    @if ($sale->is_igst)
                        <tr><td>IGST</td><td class="right">{{ money($sale->igst_total) }}</td></tr>
                    @else
                        <tr><td>CGST</td><td class="right">{{ money($sale->cgst_total) }}</td></tr>
                        <tr><td>SGST</td><td class="right">{{ money($sale->sgst_total) }}</td></tr>
                    @endif
                    @if ((float) ($sale->freight_amount ?? 0) > 0)<tr><td>{{ $t('Freight', 'वाहतूक') }}</td><td class="right">{{ money($sale->freight_amount) }}</td></tr>@endif
                    @if ((float) ($sale->hammali_amount ?? 0) > 0)<tr><td>{{ $t('Hammali', 'हमाली') }}</td><td class="right">{{ money($sale->hammali_amount) }}</td></tr>@endif
                    @if ((float) ($sale->cash_discount ?? 0) > 0)<tr><td>{{ $t('Cash discount', 'रोख सूट') }}</td><td class="right">- {{ money($sale->cash_discount) }}</td></tr>@endif
                    <tr><td>{{ $t('Round off', 'पूर्णांक') }}</td><td class="right">{{ money($sale->round_off) }}</td></tr>
                    <tr class="grand"><td>{{ $t('Grand total', 'एकूण') }}</td><td class="right">Rs. {{ money($sale->grand_total) }}</td></tr>
                    <tr><td>{{ $t('Paid', 'भरले') }}</td><td class="right">{{ money($sale->paid_amount) }}</td></tr>
                    <tr><td><strong>{{ $t('Balance due', 'येणे बाकी') }}</strong></td><td class="right"><strong>{{ money($sale->balance) }}</strong></td></tr>
                    @if ($sale->due_date)<tr><td class="muted">{{ $t('Due date', 'देय दिनांक') }}</td><td class="right">{{ fmt_date($sale->due_date) }}@if ($sale->credit_days) ({{ $sale->credit_days }} {{ $t('days', 'दिवस') }})@endif</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top:14px">
        <tr>
            <td class="small muted" style="width:60%">
                @php($printTerms = \App\Support\TermsHtml::forPrint($sale->terms ?? null, $company->invoice_terms))
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
