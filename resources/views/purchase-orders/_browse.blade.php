<div class="space-y-3">
    <form method="GET" action="{{ route('purchase-orders.browse') }}" class="flex flex-wrap gap-2" data-ajax-list>
        <input type="date" name="from" value="{{ $from }}" class="input input-sm w-36">
        <input type="date" name="to" value="{{ $to }}" class="input input-sm w-36">
        <input type="search" name="q" value="{{ request('q') }}" class="input input-sm w-40" placeholder="{{ __('Number…') }}">
        <select name="status" class="input input-sm w-36">
            <option value="">{{ __('All status') }}</option>
            <option value="open" @selected(request('status') === 'open')>{{ __('Open') }}</option>
            <option value="converted" @selected(request('status') === 'converted')>{{ __('Converted') }}</option>
            <option value="cancelled" @selected(request('status') === 'cancelled')>{{ __('Cancelled') }}</option>
        </select>
        <button class="btn-secondary btn-sm">{{ __('Filter') }}</button>
    </form>
    <div id="list-results">@include('purchase-orders._list')</div>
</div>
