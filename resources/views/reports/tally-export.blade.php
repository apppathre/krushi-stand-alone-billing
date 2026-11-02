@php
    $selectedJs = array_values($selected);
    $tab = $tab ?? 'export';
    $importDefaults = \App\Support\ExportCatalog::defaultImportKeys();
@endphp
<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Export / Import') }}</h1>
    </x-slot>

    <div class="mb-3 flex flex-wrap gap-2 no-print">
        <a href="{{ route('reports.tally-export', ['tab' => 'export']) }}"
           class="btn-sm {{ $tab === 'export' ? 'btn-primary' : 'btn-secondary' }}">{{ __('Export') }}</a>
        <a href="{{ route('reports.tally-export', ['tab' => 'import']) }}"
           class="btn-sm {{ $tab === 'import' ? 'btn-primary' : 'btn-secondary' }}">{{ __('Import from Tally') }}</a>
    </div>

    @if (session('status'))
        <div class="mb-3 rounded-lg border border-leaf/30 bg-leaf/10 px-3 py-2 text-sm">{{ session('status') }}</div>
    @endif

    @if ($tab === 'import')
        <x-page-head class="mb-3" :title="__('Import from Tally XML')"
                     :blurb="__('Upload masters and vouchers exported from TallyPrime / ERP 9 (XML). Same screen as export.')" />

        @if (! empty($importResult))
            <div class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                <div class="card card-body">{{ __('Ledgers created') }}: <strong>{{ $importResult['ledgers_created'] ?? 0 }}</strong>
                    · {{ __('updated') }}: <strong>{{ $importResult['ledgers_updated'] ?? 0 }}</strong></div>
                <div class="card card-body">{{ __('Products created') }}: <strong>{{ $importResult['products_created'] ?? 0 }}</strong>
                    · {{ __('updated') }}: <strong>{{ $importResult['products_updated'] ?? 0 }}</strong></div>
                <div class="card card-body">{{ __('Vouchers created') }}: <strong>{{ $importResult['vouchers_created'] ?? 0 }}</strong>
                    · {{ __('skipped') }}: <strong>{{ $importResult['vouchers_skipped'] ?? 0 }}</strong></div>
            </div>
            @if (! empty($importResult['errors']))
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                    <p class="font-semibold">{{ __('Some rows had issues') }} ({{ count($importResult['errors']) }})</p>
                    <ul class="mt-1 list-disc pl-5 text-xs">
                        @foreach (array_slice($importResult['errors'], 0, 12) as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

        <div class="grid gap-4 lg:grid-cols-5" x-data="importDesk({ selected: @js($importDefaults), allKeys: @js(array_keys($importCatalog)) })">
            <form method="POST" action="{{ route('reports.tally-export.import') }}" enctype="multipart/form-data"
                  class="space-y-4 lg:col-span-3" data-busy-label="{{ __('Importing…') }}">
                @csrf
                <div class="card card-body space-y-4">
                    <h2 class="card-title !text-base">1. {{ __('Tally XML file') }}</h2>
                    <input type="file" name="file" accept=".xml,text/xml,application/xml" required class="input">
                    @error('file') <p class="error-text">{{ $message }}</p> @enderror
                    <p class="text-xs text-ink-soft">{{ __('Max 20 MB. Export from Tally: Gateway → Export → XML (masters / vouchers).') }}</p>
                </div>

                <div class="card card-body space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="card-title !mb-0 !text-base">2. {{ __('What to import') }}</h2>
                        <div class="flex gap-2 text-xs">
                            <button type="button" class="btn-secondary btn-sm" @click="selectAll()">{{ __('Select all') }}</button>
                            <button type="button" class="btn-secondary btn-sm" @click="selectDefaults()">{{ __('Recommended') }}</button>
                            <button type="button" class="btn-secondary btn-sm" @click="selected = []">{{ __('Clear') }}</button>
                        </div>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($importCatalog as $key => $meta)
                            <label class="export-item" :class="selected.includes('{{ $key }}') && 'is-on'">
                                <input type="checkbox" name="include[]" value="{{ $key }}" class="checkbox mt-0.5" x-model="selected">
                                <span>
                                    <strong>{{ __($meta['label']) }}</strong>
                                    <span class="block text-xs font-normal text-ink-soft">{{ __($meta['hint']) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('include') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn-primary" :disabled="selected.length === 0">{{ __('Import XML') }}</button>
            </form>

            <aside class="space-y-4 lg:col-span-2">
                <div class="card card-body space-y-2 text-sm">
                    <h2 class="card-title !text-base">{{ __('Not imported') }}</h2>
                    <ul class="space-y-2">
                        @foreach ($importExcluded as $row)
                            <li>
                                <strong class="text-ink">{{ __($row['label']) }}</strong>
                                <span class="block text-xs text-ink-soft">{{ __($row['reason']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="card card-body space-y-2 text-sm">
                    <h2 class="card-title !text-base">{{ __('Tips') }}</h2>
                    <ol class="list-decimal space-y-1.5 pl-5">
                        <li>{{ __('Import ledgers first (or tick ledgers + vouchers together).') }}</li>
                        <li>{{ __('Re-import skips vouchers already tagged with the same Tally number.') }}</li>
                        <li>{{ __('Sales/Purchase from Tally become journal vouchers (money only).') }}</li>
                    </ol>
                </div>
            </aside>
        </div>
    @else
        <div class="mb-3" x-data="exportDesk({
            format: @js($format),
            edition: @js($edition),
            scope: @js($scope),
            selected: @js($selectedJs),
            allKeys: @js(array_keys($catalog)),
        })">
            <x-page-head class="mb-3" :title="__('Export (Tally / Excel)')"
                         :blurb="__('Pick format and what to include. XML for TallyPrime / ERP 9; Excel (.xls) for review.')" />

            <div class="mb-3 flex flex-wrap gap-2 text-xs no-print">
                <span class="rounded-lg border border-sage bg-white px-2.5 py-1.5">{{ __('Ledgers') }}: <strong>{{ $counts['ledgers'] }}</strong></span>
                <span class="rounded-lg border border-sage bg-white px-2.5 py-1.5">{{ __('Sales in range') }}: <strong>{{ $counts['sales'] }}</strong></span>
                <span class="rounded-lg border border-sage bg-white px-2.5 py-1.5">{{ __('Purchases in range') }}: <strong>{{ $counts['purchases'] }}</strong></span>
                <span class="rounded-lg border border-sage bg-white px-2.5 py-1.5">{{ __('Posted vouchers in range') }}: <strong>{{ $counts['vouchers'] }}</strong></span>
            </div>

            <form method="GET" action="{{ route('reports.tally-export.download') }}" class="grid gap-4 lg:grid-cols-5">
                <section class="space-y-4 lg:col-span-3">
                    <div class="card card-body space-y-4">
                        <h2 class="card-title !text-base">1. {{ __('Format') }}</h2>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label class="export-choice" :class="format === 'xml' && 'is-on'">
                                <input type="radio" name="format" value="xml" class="sr-only" x-model="format">
                                <strong>{{ __('Tally XML') }}</strong>
                                <span>{{ __('Import into TallyPrime or Tally.ERP 9') }}</span>
                            </label>
                            <label class="export-choice" :class="format === 'xls' && 'is-on'">
                                <input type="radio" name="format" value="xls" class="sr-only" x-model="format">
                                <strong>{{ __('Excel (.xls)') }}</strong>
                                <span>{{ __('Open in Excel / LibreOffice — sheets for review') }}</span>
                            </label>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2" x-show="format === 'xml'" x-cloak>
                            <x-select-field name="edition" :label="__('Tally edition')" :value="$edition"
                                :options="['prime' => __('TallyPrime'), 'erp9' => __('Tally.ERP 9')]" />
                            <x-select-field name="scope" :label="__('XML pack')" :value="$scope"
                                :options="[
                                    'both' => __('Masters + vouchers'),
                                    'masters' => __('Masters only'),
                                    'vouchers' => __('Vouchers only'),
                                ]" />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-field name="from" type="date" :label="__('From')" :value="$from" required />
                            <x-field name="to" type="date" :label="__('To')" :value="$to" required />
                        </div>
                        <p class="text-xs text-ink-soft">
                            {{ __('Company') }}: <strong>{{ $companyName }}</strong>
                            <span x-show="format === 'xml'" x-cloak> — {{ __('Use the same name in Tally before import.') }}</span>
                        </p>
                    </div>

                    <div class="card card-body space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="card-title !mb-0 !text-base">2. {{ __('What to export') }}</h2>
                            <div class="flex gap-2 text-xs">
                                <button type="button" class="btn-secondary btn-sm" @click="selectAll()">{{ __('Select all') }}</button>
                                <button type="button" class="btn-secondary btn-sm" @click="selectDefaults()">{{ __('Recommended') }}</button>
                                <button type="button" class="btn-secondary btn-sm" @click="selected = []">{{ __('Clear') }}</button>
                            </div>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($catalog as $key => $meta)
                                <label class="export-item" :class="selected.includes('{{ $key }}') && 'is-on'">
                                    <input type="checkbox" name="include[]" value="{{ $key }}" class="checkbox mt-0.5"
                                           x-model="selected">
                                    <span>
                                        <strong>{{ __($meta['label']) }}</strong>
                                        <span class="block text-xs font-normal text-ink-soft">{{ __($meta['hint']) }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-ink-soft" x-text="selected.length ? ('{{ __('Selected') }}: ' + selected.length) : '{{ __('Select at least one item.') }}'"></p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn-primary" :disabled="selected.length === 0"
                                x-text="format === 'xls' ? '{{ __('Download Excel') }}' : '{{ __('Download Tally XML') }}'">
                            {{ __('Download') }}
                        </button>
                        <a href="{{ route('reports.tally-export') }}" class="btn-secondary">{{ __('Reset') }}</a>
                    </div>
                </section>

                <aside class="space-y-4 lg:col-span-2">
                    <div class="card card-body space-y-2 text-sm">
                        <h2 class="card-title !text-base">{{ __('Included when ticked') }}</h2>
                        <ul class="space-y-1.5 text-ink">
                            <template x-for="key in selected" :key="key">
                                <li class="flex gap-2"><span class="text-leaf">✓</span> <span x-text="labelFor(key)"></span></li>
                            </template>
                        </ul>
                        <p class="text-xs text-ink-soft" x-show="selected.length === 0" x-cloak>{{ __('Nothing selected yet.') }}</p>
                    </div>

                    <div class="card card-body space-y-2 text-sm">
                        <h2 class="card-title !text-base">{{ __('Not exported') }}</h2>
                        <ul class="space-y-2">
                            @foreach ($excluded as $row)
                                <li>
                                    <strong class="text-ink">{{ __($row['label']) }}</strong>
                                    <span class="block text-xs text-ink-soft">{{ __($row['reason']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="card card-body space-y-2 text-sm" x-show="format === 'xml'" x-cloak>
                        <h2 class="card-title !text-base">{{ __('Tally import steps') }}</h2>
                        <ol class="list-decimal space-y-1.5 pl-5">
                            <li>{{ __('Open the company in TallyPrime / ERP 9.') }}</li>
                            <li>{{ __('Import → Masters (if XML includes ledgers).') }}</li>
                            <li>{{ __('Import → Transactions / Vouchers.') }}</li>
                            <li>{{ __('Check Tally.imp if something fails.') }}</li>
                        </ol>
                    </div>
                </aside>
            </form>
        </div>
    @endif

    <script>
        function exportDesk(cfg) {
            const labels = @js(collect($catalog)->map(fn ($m) => __($m['label']))->all());
            const defaults = @js(\App\Support\ExportCatalog::defaultIncludeKeys());
            return {
                format: cfg.format,
                edition: cfg.edition,
                scope: cfg.scope,
                selected: cfg.selected.slice(),
                allKeys: cfg.allKeys,
                selectAll() { this.selected = this.allKeys.slice(); },
                selectDefaults() { this.selected = defaults.slice(); },
                labelFor(key) { return labels[key] || key; },
            };
        }
        function importDesk(cfg) {
            const defaults = @js($importDefaults);
            return {
                selected: cfg.selected.slice(),
                allKeys: cfg.allKeys,
                selectAll() { this.selected = this.allKeys.slice(); },
                selectDefaults() { this.selected = defaults.slice(); },
            };
        }
    </script>

    <style>
        .export-choice, .export-item {
            display: flex; gap: 0.65rem; align-items: flex-start;
            border: 1px solid rgb(197 217 203 / 0.95);
            border-radius: 12px; padding: 0.75rem 0.85rem;
            background: #fff; cursor: pointer; transition: border-color .15s, background .15s;
        }
        .export-choice strong, .export-item strong { display: block; color: var(--leaf-dark); font-size: 0.9rem; }
        .export-choice span, .export-item span { font-size: 0.75rem; color: var(--ink-soft); }
        .export-choice.is-on, .export-item.is-on {
            border-color: var(--leaf); background: rgb(var(--sage-rgb) / 0.45);
        }
    </style>
</x-app-layout>
