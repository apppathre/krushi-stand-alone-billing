<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $company->display_name }} — {{ __('Your outstanding') }}</title>
    <style>
        :root { --leaf:#1f6b4a; --ink:#1a2e24; --soft:#5a6f64; --line:#d7e4dc; --bg:#f4f8f5; --card:#fff; --accent:#b45309; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: "Segoe UI", system-ui, sans-serif; color:var(--ink); background: linear-gradient(160deg,#eef6f1,#f7faf8 40%,#e8f1eb); min-height:100vh; }
        .wrap { max-width: 40rem; margin: 0 auto; padding: 1.25rem 1rem 2.5rem; }
        .brand { margin-bottom: 1rem; }
        .brand h1 { margin:0; font-size:1.45rem; color:var(--leaf); letter-spacing:-0.02em; }
        .brand p { margin:0.25rem 0 0; color:var(--soft); font-size:0.9rem; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:1.1rem 1.15rem; margin-bottom:0.85rem; box-shadow:0 8px 24px rgba(31,107,74,.06); }
        .amt { font-size:1.85rem; font-weight:700; font-variant-numeric:tabular-nums; color:var(--accent); }
        .muted { color:var(--soft); font-size:0.85rem; }
        table { width:100%; border-collapse:collapse; font-size:0.88rem; }
        th, td { padding:0.45rem 0.2rem; border-bottom:1px solid var(--line); text-align:left; }
        th { color:var(--soft); font-weight:600; font-size:0.72rem; text-transform:uppercase; letter-spacing:.04em; }
        td.num, th.num { text-align:right; font-variant-numeric:tabular-nums; }
        .pay-box { background:#ecfdf5; border:1px solid #a7f3d0; border-radius:12px; padding:0.85rem 1rem; font-size:0.9rem; }
        .pay-box strong { display:block; margin-bottom:0.35rem; color:var(--leaf); }
        .terms { font-size:0.82rem; color:var(--soft); line-height:1.45; }
        .terms ul { margin:0.35rem 0 0; padding-left:1.1rem; }
        .foot { margin-top:1rem; text-align:center; font-size:0.75rem; color:var(--soft); }
    </style>
</head>
<body>
<div class="wrap">
    <header class="brand">
        <h1>{{ $company->display_name }}</h1>
        <p>
            @if ($company->phone) {{ $company->phone }} · @endif
            {{ __('Statement for') }} <strong>{{ $party->display_name }}</strong>
        </p>
    </header>

    <section class="card">
        <p class="muted" style="margin:0 0 .35rem">{{ __('Current outstanding') }}</p>
        <div class="amt">₹ {{ money($amount) }}</div>
        <p class="muted" style="margin:.4rem 0 0">{{ $label }} · {{ __('Link valid till') }} {{ optional($token->expires_at)->format('d M Y, h:i A') }}</p>
    </section>

    @if (! empty($bills))
        <section class="card">
            <p style="margin:0 0 .6rem;font-weight:650">{{ __('Open invoices') }}</p>
            <table>
                <thead>
                <tr>
                    <th>{{ __('Invoice') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Due') }}</th>
                    <th class="num">{{ __('Balance') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($bills as $b)
                    <tr>
                        <td>{{ $b['no'] }}</td>
                        <td>{{ $b['date'] ? \Carbon\Carbon::parse($b['date'])->format('d/m/Y') : '—' }}</td>
                        <td>{{ $b['due'] ? \Carbon\Carbon::parse($b['due'])->format('d/m/Y') : '—' }}</td>
                        <td class="num">₹ {{ money($b['balance']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if ($allowPayment)
        <section class="card">
            <div class="pay-box">
                <strong>{{ __('How to pay') }}</strong>
                @if ($company->upi_id)
                    <div>{{ __('UPI') }}: <code>{{ $company->upi_id }}</code></div>
                @endif
                @if ($company->bank_name || $company->bank_account_no)
                    <div style="margin-top:.35rem">
                        @if ($company->bank_name){{ $company->bank_name }}@endif
                        @if ($company->bank_account_no) · A/c {{ $company->bank_account_no }}@endif
                        @if ($company->bank_ifsc) · IFSC {{ $company->bank_ifsc }}@endif
                        @if ($company->bank_branch) · {{ $company->bank_branch }}@endif
                    </div>
                @endif
                @if (! $company->upi_id && ! $company->bank_account_no)
                    <div>{{ __('Please pay at the shop or contact us for bank / UPI details.') }}</div>
                @endif
                @if ($company->phone)
                    <div style="margin-top:.45rem">{{ __('Call') }}: <a href="tel:{{ preg_replace('/[^\d+]/', '', (string) $company->phone) }}">{{ $company->phone }}</a></div>
                @endif
            </div>
        </section>
    @endif

    @if (filled(trim(strip_tags($termsHtml ?? ''))))
        <section class="card">
            <p style="margin:0 0 .4rem;font-weight:650">{{ __('Terms') }}</p>
            <div class="terms">{!! $termsHtml !!}</div>
        </section>
    @endif

    <p class="foot">{{ __('This private link was shared by the shop. Do not forward if it contains payment details you prefer to keep private.') }}</p>
</div>
</body>
</html>
