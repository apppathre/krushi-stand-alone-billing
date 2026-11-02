<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('GST Exceptions') }}</h1>
            <p class="text-xs text-ink-soft">
                {{ __('Audit from Sales / Purchases tables (bill + lines). Fix missing HSN, GSTIN, tax split, or rate mismatches before filing.') }}
            </p>
        </div>
    </x-slot>

    <x-auto-filter>
        <x-field name="from" type="date" :label="__('From')" :value="$from" class="w-40" />
        <x-field name="to" type="date" :label="__('To')" :value="$to" class="w-40" />
        <x-select-field name="godown_id" :label="__('Place')" :value="request('godown_id')" :placeholder="__('All')"
            :options="$godowns->mapWithKeys(fn ($g) => [$g->id => $g->display_name])->all()" class="w-44" />
        <x-select-field name="doc" :label="__('Document')" :value="$doc"
            :options="['all' => __('All'), 'sale' => __('Sales'), 'purchase' => __('Purchases')]" class="w-40" />
        <x-select-field name="type" :label="__('Exception type')" :value="$type"
            :options="collect($typeOptions)->mapWithKeys(fn ($o) => [$o['key'] => $o['label']])->all()" class="w-56" />
        <x-slot:actions>
            <x-report-export />
        </x-slot:actions>
    </x-auto-filter>

    <div id="list-results" class="space-y-4">
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ($typeOptions as $opt)
                @continue($opt['key'] === 'all')
                @php($n = (int) ($summary[$opt['key']] ?? 0))
                <div class="card card-body py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">{{ $opt['label'] }}</p>
                    <p class="font-display text-2xl font-semibold {{ $n > 0 ? 'text-accent' : 'text-leaf-dark' }}">{{ $n }}</p>
                </div>
            @endforeach
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-sage bg-sage/40 px-4 py-2 text-sm font-semibold text-leaf-dark">
                {{ __('Exception list') }}
                <span class="font-normal text-ink-soft">({{ $rows->count() }})</span>
            </div>
            <div class="table-wrap border-0">
                <table class="table text-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Doc') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Number') }}</th>
                            <th>{{ __('Party') }}</th>
                            <th>{{ __('GSTIN') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Detail') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $r)
                            <tr>
                                <td class="uppercase text-xs font-semibold text-ink-soft">{{ $r['doc'] }}</td>
                                <td>{{ fmt_date($r['date']) }}</td>
                                <td>
                                    <a href="{{ $r['url'] }}" class="font-semibold text-leaf hover:underline">{{ $r['number'] }}</a>
                                </td>
                                <td>{{ $r['party'] }}</td>
                                <td class="font-mono text-xs">{{ $r['gstin'] }}</td>
                                <td><span class="badge-amber">{{ $r['type_label'] }}</span></td>
                                <td class="max-w-md text-ink-soft">{{ $r['detail'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-ink-soft">
                                    {{ __('No GST exceptions in this period — books look clean for filing.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
