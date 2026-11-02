<form method="POST"
      action="{{ ($creating ?? false) ? route('document-series.store') : route('document-series.update', $series) }}"
      data-ajax-form
      class="space-y-4">
    @csrf
    @unless ($creating ?? false) @method('PUT') @endunless

    <div data-form-errors class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" hidden></div>

    <div class="form-callout">
        <p class="form-callout__title">
            @if ($creating ?? false)
                {{ __('New number format') }}
            @else
                {{ $labels[$series->doc_type] ?? $series->doc_type }}
            @endif
        </p>
        <p class="mb-0">{{ __('Year') }}: <strong>{{ $fy?->name ?? $series->financialYear?->name }}</strong></p>
    </div>

    <input type="hidden" name="financial_year_id" value="{{ $series->financial_year_id }}">

    @if ($creating ?? false)
        <x-select-field name="doc_type" :label="__('Document type')" :value="$series->doc_type" required
            :options="$typeOptions" />
    @else
        <input type="hidden" name="doc_type" value="{{ $series->doc_type }}">
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="name" :label="__('Format name')" :value="$series->name" required class="sm:col-span-2"
                 :hint="__('e.g. Main counter, Godown 2 series')" />
        <x-field name="prefix" :label="__('Prefix')" :value="$series->prefix" required />
        <x-field name="padding" type="number" min="1" max="8" :label="__('Padding')" :value="$series->padding" required />
        <x-field name="next_number" type="number" min="1" :label="__('Next number')" :value="$series->next_number" required class="sm:col-span-2" />
    </div>

    <div class="flex flex-wrap gap-6">
        <x-check name="is_default" :label="__('Default for this document type')" :checked="$series->is_default" />
        <x-check name="is_active" :label="__('Active')" :checked="$series->is_active ?? true" />
    </div>

    <div class="flex flex-wrap gap-2 border-t border-sage pt-4">
        <button type="submit" class="btn-primary">{{ __('Save') }}</button>
        <button type="button" class="btn-secondary" onclick="window.Alpine && Alpine.store('ui').closeDrawer()">{{ __('Cancel') }}</button>
    </div>
</form>
