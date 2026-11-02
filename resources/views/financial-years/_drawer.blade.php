<form method="POST"
      action="{{ $year->exists ? route('financial-years.update', $year) : route('financial-years.store') }}"
      data-ajax-form
      class="space-y-5"
      data-busy-label="{{ __('Saving…') }}">
    @csrf
    @if ($year->exists) @method('PUT') @endif

    <div data-form-errors class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" hidden></div>

    <div class="form-callout" role="note">
        <p class="form-callout__title">{{ __('Financial year') }}</p>
        <p class="mb-0">{{ __('India default is April–March. Saving also seeds Transaction Master series for this year.') }}</p>
    </div>

    <div class="drawer-section">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" :label="__('Name')" :value="old('name', $year->name)" required placeholder="2025-26" />
            <div></div>
            <x-field name="start_date" type="date" :label="__('Start date')" :value="old('start_date', optional($year->start_date)->toDateString())" required />
            <x-field name="end_date" type="date" :label="__('End date')" :value="old('end_date', optional($year->end_date)->toDateString())" required />
        </div>
        <div class="mt-4">
            <x-check name="make_current" :label="__('Make this the active year')" :checked="old('make_current', ! $year->exists)" />
        </div>
    </div>

    <div class="flex flex-wrap gap-2 border-t border-sage pt-4">
        <button type="submit" class="btn-primary" data-busy-label="{{ __('Saving…') }}">{{ __('Save') }}</button>
        <button type="button" class="btn-secondary" onclick="window.Alpine && Alpine.store('ui').closeDrawer()">{{ __('Cancel') }}</button>
    </div>
</form>
