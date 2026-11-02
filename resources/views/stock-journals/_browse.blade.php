<div class="space-y-3">
    <form method="GET" action="{{ route('stock-journals.'.$type.'.browse') }}" class="flex flex-wrap gap-2" data-ajax-list>
        <input type="date" name="from" value="{{ $from }}" class="input input-sm w-36">
        <input type="date" name="to" value="{{ $to }}" class="input input-sm w-36">
        <input type="search" name="q" value="{{ request('q') }}" class="input input-sm w-40" placeholder="{{ __('Number…') }}">
        <button class="btn-secondary btn-sm">{{ __('Filter') }}</button>
    </form>
    <div id="list-results">@include('stock-journals._list')</div>
</div>
