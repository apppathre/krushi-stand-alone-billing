<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Financial Year') }}</h1>
            <p class="text-xs text-ink-soft">{{ __('Active FY drives voucher dates and numbering — same as TMS / ERP.') }}</p>
        </div>
    </x-slot>

    <div class="form-callout mb-4" role="note">
        <p class="form-callout__title">{{ __('Active year') }}: {{ $current->name }}</p>
        <p class="mb-0">{{ fmt_date($current->start_date) }} → {{ fmt_date($current->end_date) }}. {{ __('Sale / purchase dates must fall in this range.') }}</p>
    </div>

    <div class="filter-bar card card-body mb-4">
        <div class="filter-bar__fields">
            <p class="mb-0 text-sm text-ink-soft">{{ __('Manage years here — numbering lives under Transaction Master.') }}</p>
        </div>
        <div class="filter-bar__actions">
            @can('menu', ['financial_years', 'add'])
                <form method="POST" action="{{ route('financial-years.create-next') }}" data-busy-label="{{ __('Creating…') }}" class="inline">
                    @csrf
                    <input type="hidden" name="activate" value="0">
                    <button type="submit" class="btn-secondary btn-sm">{{ __('Next year') }}</button>
                </form>
                <button type="button" class="btn-primary btn-sm"
                        onclick="KrushiDrawer.open('{{ route('financial-years.create') }}', '{{ __('Add financial year') }}', '32rem')">
                    <x-icon name="plus" class="h-4 w-4" /> <span class="hidden sm:inline">{{ __('Add') }}</span>
                </button>
            @endcan
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Year') }}</th>
                    <th>{{ __('Start') }}</th>
                    <th>{{ __('End') }}</th>
                    <th class="num">{{ __('Series') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="num">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($years as $y)
                    <tr>
                        <td class="font-semibold">{{ $y->name }}</td>
                        <td>{{ fmt_date($y->start_date) }}</td>
                        <td>{{ fmt_date($y->end_date) }}</td>
                        <td class="num">{{ $y->document_series_count }}</td>
                        <td>
                            @if ($y->is_current)
                                <span class="badge-green">{{ __('Active') }}</span>
                            @else
                                <span class="badge-gray">{{ __('Off') }}</span>
                            @endif
                        </td>
                        <td class="num whitespace-nowrap">
                            @can('menu', ['financial_years', 'edit'])
                                <button type="button" class="btn-secondary btn-sm"
                                        onclick="KrushiDrawer.open('{{ route('financial-years.edit', $y) }}', '{{ __('Edit financial year') }}', '32rem')">
                                    {{ __('Edit') }}
                                </button>
                                @unless ($y->is_current)
                                    <form method="POST" action="{{ route('financial-years.activate', $y) }}" class="inline" data-busy-label="{{ __('Saving…') }}">
                                        @csrf
                                        <button type="submit" class="btn-secondary btn-sm">{{ __('Make active') }}</button>
                                    </form>
                                @endunless
                            @endcan
                            @can('menu', ['financial_years', 'delete'])
                                @unless ($y->is_current)
                                    <form method="POST" action="{{ route('financial-years.destroy', $y) }}" class="inline" onsubmit="return confirm('{{ __('Delete this record?') }}')">
                                        @csrf @method('DELETE')
                                        <button class="btn-danger btn-sm">{{ __('Delete') }}</button>
                                    </form>
                                @endunless
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
