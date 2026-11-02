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
    <title>{{ $t('Delivery Challan', 'डिलिव्हरी चलन') }} · {{ $sale->invoice_no }}</title>
    <style>
        @page { margin: {{ $size === 'quarter' ? '10px 12px' : ($size === 'half' ? '14px 16px' : '24px 28px') }}; }
        body { font-family: DejaVu Sans, sans-serif; font-size: {{ $size === 'quarter' ? '8px' : ($size === 'half' ? '9px' : '10px') }}; color: #1F2A24; }
        h1 { font-size: {{ $size === 'quarter' ? '14px' : ($size === 'half' ? '16px' : '20px') }}; margin: 0; color: #214D39; }
        .muted { color: #5B6A61; }
        .right { text-align: right; }
        .center { text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .title { background: #3D5A4A; color: #fff; padding: 5px 8px; font-size: 12px; font-weight: bold; letter-spacing: 1px; }
        .box { border: 1px solid #CFE0D4; padding: 6px 8px; }
        .items th { background: #E8F0EA; color: #214D39; border: 1px solid #CFE0D4; padding: 4px 5px; font-size: 9px; }
        .items td { border: 1px solid #CFE0D4; padding: 4px 5px; vertical-align: top; }
        .small { font-size: 8.5px; }
        .note { margin-top: 10px; font-size: 8.5px; color: #5B6A61; }
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
                <div><strong>GSTIN:</strong> {{ $company->gstin }}</div>
            </td>
            <td class="right">
                <div class="title center">{{ $t('DELIVERY CHALLAN', 'डिलिव्हरी चलन') }}</div>
                <table style="margin-top:6px">
                    <tr><td class="muted">{{ $t('Against invoice', 'बील क्र.') }}</td><td class="right"><strong>{{ $sale->invoice_no }}</strong></td></tr>
                    <tr><td class="muted">{{ $t('Date', 'दिनांक') }}</td><td class="right">{{ fmt_date($sale->invoice_date) }}</td></tr>
                    @if ($sale->status === 'cancelled')<tr><td colspan="2" class="right" style="color:#b91c1c"><strong>{{ $t('CANCELLED', 'रद्द') }}</strong></td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    <div class="box" style="margin-top:10px">
        <strong>{{ $t('Deliver to', 'वितरित करा') }}:</strong>
        {{ $sale->customer_name ?: ($mr && $sale->client->name_mr ? $sale->client->name_mr : $sale->client->name) }}
        @if ($sale->client->village || $sale->client->city)
            <span class="muted">&mdash; {{ collect([$sale->client->village, $sale->client->city])->filter()->implode(', ') }}</span>
        @endif
        @if ($sale->customer_phone ?: $sale->client->phone)
            <span class="muted">| {{ $t('Ph', 'फोन') }}: {{ $sale->customer_phone ?: $sale->client->phone }}</span>
        @endif
    </div>

    <table class="items" style="margin-top:10px">
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th>{{ $t('Item', 'वस्तू') }}</th>
                <th>{{ $t('Batch', 'बॅच') }}</th>
                <th>{{ $t('Place', 'गोडाउन') }}</th>
                <th>{{ $t('Location / Rack', 'लोकेशन / रॅक') }}</th>
                <th class="right">{{ $t('Qty', 'नग') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $i => $it)
                @php
                    $placeName = $it->godown?->display_name
                        ?: ($sale->godown?->display_name ?? '—');
                    $rackLabel = $it->batch?->rack?->label ?? '—';
                @endphp
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>
                        <strong>{{ $mr && $it->product->name_mr ? $it->product->name_mr : $it->product->name }}</strong>
                        @if ($it->hsn_code)<div class="small muted">HSN {{ $it->hsn_code }}</div>@endif
                    </td>
                    <td>
                        @if ($it->batch && $it->batch->batch_no !== 'NA')
                            {{ $it->batch->batch_no }}
                            @if ($it->batch->expiry_date)
                                <div class="small muted">E {{ $it->batch->expiry_date->format('m-Y') }}</div>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $placeName }}</td>
                    <td>{{ $rackLabel }}</td>
                    <td class="right">{{ qty($it->qty) }} {{ $it->product->unit?->short_name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="note">
        {{ $t(
            'This delivery challan / note shows pick locations for warehouse use. Tax amounts are on the Tax Invoice only.',
            'हे डिलिव्हरी चलन गोडाउन पिकसाठी आहे. कर रक्कम फक्त कर बीलावर आहे.'
        ) }}
    </p>

    <table style="margin-top:28px">
        <tr>
            <td style="width:50%">
                <div class="muted small">{{ $t('Received by (name / sign)', 'स्वीकारकर्ता नाव / सही') }}</div>
                <br><br>
                <div style="border-top:1px solid #CFE0D4; width:70%"></div>
            </td>
            <td class="right">
                {{ $t('For', 'करीता') }} <strong>{{ $shop }}</strong><br><br>
                <span class="small muted">{{ $t('Authorised signatory', 'अधिकृत सही') }}</span>
            </td>
        </tr>
    </table>
</body>
</html>
