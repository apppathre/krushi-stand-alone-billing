@php
    $perPage = $perPage ?? (int) request('per_page', 10);
    $fyStart = \App\Models\FinancialYear::current()->start_date->toDateString();
    $drawerTitle = __('Recent').' — '.($title ?? '');
    $drawerWidth = '72rem';
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
                    onclick="KrushiDrawer.open(@js(route('stock-journals.'.$type.'.browse', ['preset' => $key, 'per_page' => $perPage])), @js($drawerTitle), @js($drawerWidth))">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <form method="GET" action="{{ route('stock-journals.'.$type.'.browse') }}" class="space-y-2"
          data-fy-start="{{ $fyStart }}"
          data-today="{{ $to }}"
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
            <input type="search" name="q" value="{{ request('q') }}" class="input input-sm w-40" placeholder="{{ __('Number…') }}">
            <label class="block text-xs">
                <span class="text-ink-500">{{ __('Show') }}</span>
                <select name="per_page" class="input input-sm mt-0.5 w-20">
                    @foreach ([10, 20, 50] as $n)
                        <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </form>
    @include('stock-journals._list')
</div>
