@php
    $perPage = $perPage ?? (int) request('per_page', 10);
    $fy = \App\Models\FinancialYear::current();
    $fyStart = $fy->start_date->toDateString();
    $drawerTitle = __('Recent invoices');
    $drawerWidth = '80rem';
    $activePreset = request('preset');
    if (! $activePreset && ! request()->filled('from') && ! request()->filled('to')) {
        $activePreset = 'month';
    }
@endphp
<div class="space-y-3">
    <div class="flex flex-wrap gap-1.5">
        @foreach (['today' => __('Today'), 'yesterday' => __('Yesterday'), 'month' => __('This month'), 'fy' => __('This year')] as $key => $label)
            <button type="button"
                    class="btn-secondary btn-sm {{ $activePreset === $key ? 'ring-2 ring-leaf' : '' }}"
                    onclick="KrushiDrawer.open(@js(route('sales.browse', ['preset' => $key, 'per_page' => $perPage])), @js($drawerTitle), @js($drawerWidth))">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <form method="GET" action="{{ route('sales.browse') }}" class="space-y-2"
          data-fy-start="{{ $fyStart }}"
          data-today="{{ $to }}"
          data-month-start="{{ \Illuminate\Support\Carbon::parse($to)->startOfMonth()->toDateString() }}"
          x-data="autoFilter('#list-results')"
          @input.debounce.350ms="run()" @change="run()" @submit.prevent="run()">
        <div class="flex flex-wrap items-end gap-2">
            <label class="block text-xs">
                <span class="text-ink-500">{{ __('Date from') }}</span>
                <input type="date" name="from" value="{{ $from }}" class="input input-sm mt-0.5 w-36">
            </label>
            <label class="block text-xs">
                <span class="text-ink-500">{{ __('Date to') }}</span>
                <input type="date" name="to" value="{{ $to }}" class="input input-sm mt-0.5 w-36">
            </label>
            <label class="block min-w-[10rem] flex-1 text-xs">
                <span class="text-ink-500">{{ __('Client') }}</span>
                <select name="client_id" class="input input-sm mt-0.5 w-full">
                    <option value="">{{ __('All clients') }}</option>
                    @foreach ($filterClients as $c)
                        <option value="{{ $c->id }}" @selected((string) request('client_id') === (string) $c->id)>{{ $c->display_name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-xs">
                <span class="text-ink-500">{{ __('Show') }}</span>
                <select name="per_page" class="input input-sm mt-0.5 w-20">
                    @foreach ([10, 20, 50] as $n)
                        <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </label>
            <input type="search" name="q" value="{{ request('q') }}" class="input input-sm w-36" placeholder="{{ __('Invoice / search…') }}" autocomplete="off">
        </div>
    </form>
    @include('sales._list')
</div>
