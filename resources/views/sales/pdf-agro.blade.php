@php
    $lang = $printLang ?? 'mr';
    $mr = $lang === 'mr';
    $shop = filled($company->name_mr) ? $company->name_mr : $company->name;
    $shopEn = $company->name;
    $party = $sale->client;
    $partyName = $sale->customer_name ?: (($mr && $party?->name_mr) ? $party->name_mr : $party?->name);
    $hsnGroups = $sale->items->groupBy(fn ($it) => $it->hsn_code ?: '—');
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->invoice_no }}</title>
    <style>
        @page { margin: 12px 14px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #111; }
        .red { color: #b91c1c; }
        .muted { color: #555; }
        .right { text-align: right; }
        .center { text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        .border td, .border th { border: 1px solid #333; }
        .head-name { font-size: 18px; font-weight: bold; color: #b91c1c; margin: 0; }
        .head-en { font-size: 10px; font-weight: bold; margin: 0; }
        .lic { font-size: 7.5px; line-height: 1.35; }
        .tag { text-align: center; font-size: 9px; font-weight: bold; margin: 4px 0; border-top: 1px solid #333; border-bottom: 1px solid #333; padding: 3px; }
        .party td { vertical-align: top; padding: 2px 4px; }
        .items th { background: #f3f3f3; font-size: 7px; padding: 3px 2px; border: 1px solid #333; }
        .items td { padding: 3px 2px; border: 1px solid #333; vertical-align: top; }
        .box { border: 1px solid #333; padding: 4px; }
        .small { font-size: 7.5px; }
        .grand { font-size: 11px; font-weight: bold; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width:58%; vertical-align:top">
                <p class="head-name">{{ $shop }}</p>
                @if ($shopEn !== $shop)<p class="head-en">{{ $shopEn }}</p>@endif
                <div class="small">
                    {{ $company->address }}{{ $company->city ? ', '.$company->city : '' }} {{ $company->pincode }}<br>
                    @if ($company->phone) Mob: {{ $company->phone }} @endif
                    @if ($company->gstin) | GST No: {{ $company->gstin }} @endif<br>
                    Place of Supply: {{ $company->state_name }} (Code: {{ $company->state_code }})
                </div>
            </td>
            <td style="width:42%; vertical-align:top" class="lic right">
                <strong>TAX INVOICE / कर बील</strong><br>
                @if ($company->fertilizer_licence_no) Fert Lic: {{ $company->fertilizer_licence_no }}<br>@endif
                @if ($company->pesticide_licence_no) Pest Lic: {{ $company->pesticide_licence_no }}<br>@endif
                @if ($company->seed_licence_no) Seed Lic: {{ $company->seed_licence_no }}<br>@endif
                <span class="muted">IRN / Ack / e-Invoice: —</span>
            </td>
        </tr>
    </table>

    @if ($company->tagline)
        <div class="tag">{{ $company->tagline }}</div>
    @else
        <div class="tag">All type of Fertilizers, Pesticides and Seeds</div>
    @endif

    <table class="party border" style="margin-top:4px">
        <tr>
            <td style="width:55%">
                <strong>ग्राहक / Customer:</strong> {{ $partyName }}<br>
                @if ($party?->address || $party?->village || $party?->city)
                    {{ collect([$party->address, $party->village, $party->city])->filter()->implode(', ') }}<br>
                @endif
                @if ($sale->customer_phone ?: $party?->phone) Mobile: {{ $sale->customer_phone ?: $party->phone }} @endif
                @if ($party?->gstin) | GSTIN: {{ $party->gstin }} @endif
            </td>
            <td>
                <table>
                    <tr><td>Invoice No</td><td class="right"><strong>{{ $sale->invoice_no }}</strong></td></tr>
                    <tr><td>Date</td><td class="right">{{ fmt_date($sale->invoice_date) }}</td></tr>
                    <tr><td>Type</td><td class="right">{{ $sale->is_igst ? 'IGST' : 'CGST+SGST' }} Sale</td></tr>
                    <tr><td>Supply</td><td class="right">{{ \App\Support\IndianStates::name($sale->supply_state_code) }} ({{ $sale->supply_state_code }})</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top:6px">
        <thead>
            <tr>
                <th>अ.क्र<br>Sr</th>
                <th>विवरण<br>Description</th>
                <th>उत्पादक<br>Mfr</th>
                <th>विपणन<br>Mkt</th>
                <th>पॅकींग<br>Pack</th>
                <th>बॅच<br>Batch</th>
                <th>उत्पाद<br>Mfg</th>
                <th>वैध<br>Exp</th>
                <th>HSN</th>
                <th>GST%</th>
                <th>नग<br>Qty</th>
                <th>दर<br>Rate</th>
                <th>रक्कम<br>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $i => $it)
                @php $p = $it->product; @endphp
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>
                        <strong>{{ $mr && $p->name_mr ? $p->name_mr : $p->name }}</strong>
                        @if ($p->usage)<div class="muted">{{ $p->usage }}</div>@endif
                    </td>
                    <td>{{ $p->manufacturer?->display_name }}</td>
                    <td>{{ $p->marketed_by ?: $p->manufacturer?->display_name }}</td>
                    <td>{{ $p->packing }}</td>
                    <td>{{ $it->batch && $it->batch->batch_no !== 'NA' ? $it->batch->batch_no : '' }}</td>
                    <td>{{ $it->batch?->mfg_date?->format('d-m-Y') }}</td>
                    <td>{{ $it->batch?->expiry_date?->format('d-m-Y') }}</td>
                    <td>{{ $it->hsn_code }}</td>
                    <td class="right">{{ qty($it->gst_rate) }}</td>
                    <td class="right">{{ qty($it->qty) }} {{ $p->unit?->short_name }}</td>
                    <td class="right">{{ money($it->rate) }}</td>
                    <td class="right">{{ money($it->taxable_amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top:6px">
        <tr>
            <td style="width:48%; vertical-align:top">
                <div class="box small">
                    <strong>GST Summary / जीएसटी सारांश</strong>
                    <table style="margin-top:3px">
                        <tr>
                            <th class="left">HSN</th>
                            <th class="right">Taxable</th>
                            @if ($sale->is_igst)
                                <th class="right">IGST</th>
                            @else
                                <th class="right">CGST</th>
                                <th class="right">SGST</th>
                            @endif
                        </tr>
                        @foreach ($hsnGroups as $hsn => $rows)
                            <tr>
                                <td>{{ $hsn }}</td>
                                <td class="right">{{ money($rows->sum('taxable_amount')) }}</td>
                                @if ($sale->is_igst)
                                    <td class="right">{{ money($rows->sum('igst_amount')) }}</td>
                                @else
                                    <td class="right">{{ money($rows->sum('cgst_amount')) }}</td>
                                    <td class="right">{{ money($rows->sum('sgst_amount')) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </table>
                </div>
                @if ($company->bank_name)
                    <div class="box small" style="margin-top:4px">
                        <strong>Bank:</strong> {{ $company->bank_name }}
                        | A/c {{ $company->bank_account_no }}
                        | IFSC {{ $company->bank_ifsc }}
                        @if ($company->bank_branch) | {{ $company->bank_branch }} @endif
                    </div>
                @endif
                <div class="box small" style="margin-top:4px">
                    <strong>येणे बाकी / Outstanding:</strong>
                    {{ money($partyBalance['amount'] ?? 0) }} {{ strtoupper($partyBalance['side'] ?? 'dr') }}
                </div>
            </td>
            <td style="width:4%"></td>
            <td style="width:48%; vertical-align:top">
                <table>
                    <tr><td>Sub Total / उपयोग</td><td class="right">{{ money($sale->taxable_total) }}</td></tr>
                    @if ($sale->discount_total > 0)
                        <tr><td>Discount / सूट</td><td class="right">- {{ money($sale->discount_total) }}</td></tr>
                    @endif
                    @if ($sale->is_igst)
                        <tr><td>IGST</td><td class="right">{{ money($sale->igst_total) }}</td></tr>
                    @else
                        <tr><td>CGST Output</td><td class="right">{{ money($sale->cgst_total) }}</td></tr>
                        <tr><td>SGST Output</td><td class="right">{{ money($sale->sgst_total) }}</td></tr>
                    @endif
                    @if ((float) ($sale->freight_amount ?? 0) > 0)<tr><td>Freight / वाहतूक</td><td class="right">{{ money($sale->freight_amount) }}</td></tr>@endif
                    @if ((float) ($sale->hammali_amount ?? 0) > 0)<tr><td>Hammali / हमाली</td><td class="right">{{ money($sale->hammali_amount) }}</td></tr>@endif
                    @if ((float) ($sale->cash_discount ?? 0) > 0)<tr><td>Cash Disc / रोख सूट</td><td class="right">- {{ money($sale->cash_discount) }}</td></tr>@endif
                    <tr><td>Round Off</td><td class="right">{{ money($sale->round_off) }}</td></tr>
                    <tr class="grand"><td>Grand Total / एकूण</td><td class="right">{{ money($sale->grand_total) }}</td></tr>
                    <tr><td>Paid / भरले</td><td class="right">{{ money($sale->paid_amount) }}</td></tr>
                    <tr><td><strong>Balance / बाकी</strong></td><td class="right"><strong>{{ money($sale->balance) }}</strong></td></tr>
                </table>
                <div class="box small" style="margin-top:4px">
                    <strong>In words:</strong> {{ amount_in_words((float) $sale->grand_total) }}
                </div>
            </td>
        </tr>
    </table>

    <table style="margin-top:10px">
        <tr>
            <td style="width:55%; vertical-align:top" class="small muted">
                @php($printTerms = \App\Support\TermsHtml::forPrint($sale->terms ?? null, $company->invoice_terms))
                @if ($printTerms)
                    <strong>Terms / अटी:</strong>
                    <div class="terms-html">{!! $printTerms !!}</div>
                @endif
            </td>
            <td class="right" style="vertical-align:bottom">
                <div>ग्राहकाची सही / Customer</div>
                <br><br>
                <div>करीता / For <strong>{{ $shop }}</strong></div>
                <div class="muted small">Authorised signatory</div>
            </td>
        </tr>
    </table>
</body>
</html>
