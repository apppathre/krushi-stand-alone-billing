<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('GST returns') }}</h1>
            <p class="text-xs text-ink-soft">{{ __('GSTR-1 style outward (B2B / B2C / credit notes) + inward purchases + HSN summary.') }}</p>
        </div>
    </x-slot>

    <x-auto-filter>
        <x-field name="from" type="date" :label="__('From')" :value="$from" class="w-40" />
        <x-field name="to" type="date" :label="__('To')" :value="$to" class="w-40" />
        <x-slot:actions>
            <x-report-export />
        </x-slot:actions>
    </x-auto-filter>

    @php($s = $pack['summary'])
    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card card-body"><p class="text-xs text-ink-soft">{{ __('Outward bills') }}</p><p class="font-display text-xl font-semibold">{{ $s['bills'] }}</p></div>
        <div class="card card-body"><p class="text-xs text-ink-soft">{{ __('Outward taxable') }}</p><p class="font-semibold">{{ money($s['taxable']) }}</p></div>
        <div class="card card-body"><p class="text-xs text-ink-soft">CGST / SGST / IGST</p><p class="text-sm font-semibold">{{ money($s['cgst']) }} / {{ money($s['sgst']) }} / {{ money($s['igst']) }}</p></div>
        <div class="card card-body"><p class="text-xs text-ink-soft">{{ __('Inward taxable') }}</p><p class="font-semibold">{{ money($s['in_taxable']) }}</p></div>
    </div>

    @foreach ([
        'b2b' => __('B2B (registered)'),
        'b2cl' => __('B2CL (unregistered >= 2.5L)'),
        'b2c' => __('B2C (unregistered)'),
        'cdnr' => __('CDNR (credit notes - registered)'),
        'cdnur' => __('CDNUR (credit notes - unregistered)'),
        'purchases' => __('Purchases (inward)'),
        'purchase_returns' => __('Purchase returns'),
    ] as $key => $title)
        @php($rows = $pack[$key] ?? [])
        <section class="card mb-4">
            <div class="border-b border-sage px-4 py-2 font-semibold text-leaf-dark">{{ $title }} <span class="text-xs font-normal text-ink-soft">({{ count($rows) }})</span></div>
            <div class="table-wrap">
                <table class="table text-sm">
                    <thead>
                        <tr>
                            <th>{{ __('No') }}</th><th>{{ __('Date') }}</th><th>{{ __('Party') }}</th><th>{{ __('GSTIN') }}</th>
                            <th class="num">{{ __('Taxable') }}</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">IGST</th><th class="num">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $r)
                            <tr>
                                <td class="font-medium">{{ $r['number'] }}@if (!empty($r['ref'])) <span class="text-xs text-ink-soft">← {{ $r['ref'] }}</span>@endif</td>
                                <td>{{ fmt_date($r['date']) }}</td>
                                <td>{{ $r['party'] }}</td>
                                <td class="font-mono text-xs">{{ $r['gstin'] ?: '—' }}</td>
                                <td class="num">{{ money($r['taxable']) }}</td>
                                <td class="num">{{ money($r['cgst']) }}</td>
                                <td class="num">{{ money($r['sgst']) }}</td>
                                <td class="num">{{ money($r['igst']) }}</td>
                                <td class="num font-semibold">{{ money($r['grand']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="py-4 text-center text-ink-soft">{{ __('No rows') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    @foreach (['hsn_out' => __('HSN summary — outward'), 'hsn_in' => __('HSN summary — inward')] as $key => $title)
        <section class="card mb-4">
            <div class="border-b border-sage px-4 py-2 font-semibold text-leaf-dark">{{ $title }}</div>
            <div class="table-wrap">
                <table class="table text-sm">
                    <thead>
                        <tr><th>HSN</th><th class="num">{{ __('Rate %') }}</th><th>{{ __('Unit') }}</th><th class="num">{{ __('Qty') }}</th>
                            <th class="num">{{ __('Taxable') }}</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">IGST</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($pack[$key] as $h)
                            <tr>
                                <td class="font-mono">{{ $h['hsn'] }}</td>
                                <td class="num">{{ qty($h['rate']) }}</td>
                                <td>{{ $h['unit'] ?: '—' }}</td>
                                <td class="num">{{ qty($h['qty']) }}</td>
                                <td class="num">{{ money($h['taxable']) }}</td>
                                <td class="num">{{ money($h['cgst']) }}</td>
                                <td class="num">{{ money($h['sgst']) }}</td>
                                <td class="num">{{ money($h['igst']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-4 text-center text-ink-soft">{{ __('No rows') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</x-app-layout>
