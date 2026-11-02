<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Transaction Master') }}</h1>
            <p class="text-xs text-ink-soft">{{ __('Number formats per document type. You can add more than one series for the same type.') }}</p>
        </div>
    </x-slot>

    <div class="form-callout mb-4" role="note">
        <p class="form-callout__title">{{ __('Numbering') }}</p>
        <p class="mb-0">{{ __('Sample') }}: <code class="rounded bg-sage px-1.5 py-0.5 text-xs">PREFIX/{{ $fy->name }}/0001</code>. {{ __('Default series is used when posting; extra formats are available as pickers later.') }}</p>
    </div>

    <div class="filter-bar card card-body mb-4">
        <form method="GET" class="filter-bar__fields">
            <x-select-field name="financial_year_id" :label="__('Financial Year')" :value="$fy->id"
                :options="$years->mapWithKeys(fn ($y) => [$y->id => $y->name.($y->is_current ? ' · '.__('Active') : '')])->all()"
                class="w-full sm:w-56" />
            <button type="submit" class="btn-secondary btn-sm">{{ __('Show') }}</button>
        </form>
        <div class="filter-bar__actions">
            @can('menu', ['document_series', 'add'])
                <button type="button" class="btn-primary btn-sm"
                        onclick="KrushiDrawer.open('{{ route('document-series.create', ['financial_year_id' => $fy->id]) }}', '{{ __('Add format') }}', '32rem')">
                    <x-icon name="plus" class="h-4 w-4" /> <span class="hidden sm:inline">{{ __('Add format') }}</span>
                </button>
                <form method="POST" action="{{ route('document-series.seed') }}" data-busy-label="{{ __('Saving…') }}">
                    @csrf
                    <input type="hidden" name="financial_year_id" value="{{ $fy->id }}">
                    <button type="submit" class="btn-secondary btn-sm">{{ __('Seed missing') }}</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="space-y-6">
        @foreach ($groupedRows as $group => $types)
            <section>
                <h2 class="mb-2 font-display text-sm font-semibold uppercase tracking-wide text-leaf-dark">
                    {{ \App\Support\TransactionTypes::groupLabel($group) }}
                </h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Document') }}</th>
                                <th>{{ __('Format name') }}</th>
                                <th>{{ __('Prefix') }}</th>
                                <th class="num">{{ __('Next no.') }}</th>
                                <th class="num">{{ __('Pad') }}</th>
                                <th>{{ __('Sample') }}</th>
                                <th>{{ __('Flags') }}</th>
                                <th class="num">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($types as $block)
                                @foreach ($block['formats'] as $row)
                                    @php
                                        $sample = sprintf('%s/%s/%s', $row->prefix, $fy->name, str_pad((string) $row->next_number, $row->padding, '0', STR_PAD_LEFT));
                                    @endphp
                                    <tr>
                                        <td class="font-semibold">{{ $labels[$block['code']] ?? $block['meta']['label'] }}</td>
                                        <td>{{ $row->name }}</td>
                                        <td>{{ $row->prefix }}</td>
                                        <td class="num">{{ $row->next_number }}</td>
                                        <td class="num">{{ $row->padding }}</td>
                                        <td><code class="text-xs">{{ $sample }}</code></td>
                                        <td class="space-x-1">
                                            @if ($row->is_default) <span class="badge-green">{{ __('Default') }}</span> @endif
                                            @unless ($row->is_active) <span class="badge-gray">{{ __('Off') }}</span> @endunless
                                        </td>
                                        <td class="num whitespace-nowrap">
                                            @can('menu', ['document_series', 'edit'])
                                                <button type="button" class="btn-secondary btn-sm"
                                                        onclick="KrushiDrawer.open('{{ route('document-series.edit', $row) }}', '{{ __('Edit series') }}', '32rem')">
                                                    {{ __('Edit') }}
                                                </button>
                                            @endcan
                                            @can('menu', ['document_series', 'delete'])
                                                @unless ($row->is_default && $block['formats']->count() === 1)
                                                    <form method="POST" action="{{ route('document-series.destroy', $row) }}" class="inline"
                                                          onsubmit="return confirm('{{ __('Delete this format?') }}')">
                                                        @csrf @method('DELETE')
                                                        <button class="btn-danger btn-sm">{{ __('Delete') }}</button>
                                                    </form>
                                                @endunless
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
</x-app-layout>
