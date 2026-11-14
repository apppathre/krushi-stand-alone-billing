@php
    $perPage = $perPage ?? (int) request('per_page', 10);
    $fyStart = \App\Models\FinancialYear::current()->start_date->toDateString();
@endphp
<div class="space-y-3">
    <form method="GET" action="{{ route('purchases.browse') }}" class="space-y-2"
          data-fy-start="{{ $fyStart }}"
          x-data="autoFilter('#list-results')"
          @input.debounce.350ms="run()" @change="run()" @submit.prevent="run()">
        <div class="flex flex-wrap gap-1.5">
            <button type="button" class="btn-secondary btn-sm" @click="setRange('today')">{{ __('Today') }}</button>
            <button type="button" class="btn-secondary btn-sm" @click="setRange('yesterday')">{{ __('Yesterday') }}</button>
            <button type="button" class="btn-secondary btn-sm" @click="setRange('month')">{{ __('This month') }}</button>
            <button type="button" class="btn-secondary btn-sm" @click="setRange('fy')">{{ __('This year') }}</button>
        </div>
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
                <span class="text-ink-500">{{ __('Supplier') }}</span>
                <select name="supplier_id" class="input input-sm mt-0.5 w-full">
                    <option value="">{{ __('All suppliers') }}</option>
                    @foreach ($filterSuppliers as $s)
                        <option value="{{ $s->id }}" @selected((string) request('supplier_id') === (string) $s->id)>{{ $s->display_name }}</option>
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
            <input type="search" name="q" value="{{ request('q') }}" class="input input-sm w-36" placeholder="{{ __('Bill / search…') }}" autocomplete="off">
        </div>
    </form>
    @include('purchases._list')
</div>
