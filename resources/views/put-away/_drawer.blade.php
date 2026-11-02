<form method="POST" action="{{ route('put-away.update', $batch) }}" data-ajax-form class="space-y-4">
    @csrf
    @method('PUT')

    <div data-form-errors class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" hidden></div>

    <div class="rounded-lg border border-sage bg-cream/40 px-3 py-2 text-sm">
        <div class="font-semibold text-leaf-dark">{{ $batch->product?->display_name }}</div>
        <div class="text-ink-soft">
            {{ __('Place') }}: {{ $batch->godown?->display_name }}
            · {{ __('Batch') }}: {{ $batch->batch_no }}
            · {{ __('Qty') }}: {{ qty($qty) }} {{ $batch->product?->unit?->short_name }}
        </div>
    </div>

    <div>
        <label class="label" for="f-rack_id">{{ __('Rack / aisle') }}</label>
        <select id="f-rack_id" name="rack_id" class="input">
            <option value="">{{ __('Not placed yet') }}</option>
            @foreach ($racks as $rack)
                <option value="{{ $rack->id }}" @selected((int) old('rack_id', $batch->rack_id) === (int) $rack->id)>{{ $rack->label }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-ink-soft">{{ __('Does not move qty — only records where the stock sits.') }}</p>
        @if ($racks->isEmpty())
            <p class="mt-1 text-xs text-amber-700">{{ __('No racks for this place. Add racks first.') }}</p>
        @endif
    </div>

    <div class="flex flex-wrap gap-2 border-t border-sage pt-4">
        <button type="submit" class="btn-primary">{{ __('Save location') }}</button>
        <button type="button" class="btn-secondary" onclick="window.Alpine && Alpine.store('ui').closeDrawer()">{{ __('Cancel') }}</button>
    </div>
</form>
