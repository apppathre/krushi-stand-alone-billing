@php($perPage = $perPage ?? (int) request('per_page', 10))
<div class="space-y-3">
    <form method="GET" action="{{ route('purchase-orders.browse') }}" class="flex flex-wrap items-end gap-2" data-ajax-list>
        <label class="block text-xs">
            <span class="text-ink-500">{{ __('Date from') }}</span>
            <input type="date" name="from" value="{{ $from }}" class="input input-sm mt-0.5 w-36">
        </label>
        <label class="block text-xs">
            <span class="text-ink-500">{{ __('Date to') }}</span>
            <input type="date" name="to" value="{{ $to }}" class="input input-sm mt-0.5 w-36">
        </label>
        <input type="search" name="q" value="{{ request('q') }}" class="input input-sm w-40" placeholder="{{ __('Number…') }}">
        <select name="status" class="input input-sm w-36">
            <option value="">{{ __('All status') }}</option>
            <option value="open" @selected(request('status') === 'open')>{{ __('Open') }}</option>
            <option value="converted" @selected(request('status') === 'converted')>{{ __('Converted') }}</option>
            <option value="cancelled" @selected(request('status') === 'cancelled')>{{ __('Cancelled') }}</option>
        </select>
        <label class="block text-xs">
            <span class="text-ink-500">{{ __('Show') }}</span>
            <select name="per_page" class="input input-sm mt-0.5 w-20">
                @foreach ([10, 20, 50] as $n)
                    <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn-secondary btn-sm">{{ __('Filter') }}</button>
    </form>
    <div id="list-results">@include('purchase-orders._list')</div>
</div>
