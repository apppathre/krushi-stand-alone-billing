{{--
  Transaction Master series picker + live next-number preview (AJAX, no increment).
  @var string $docType  config/transaction_types code (sale, purchase, credit_note, …)
  @var string $dateInput  CSS selector for voucher date field inside the form
  @var int|null $selectedFormatId
--}}
@php
    $docType = $docType ?? 'sale';
    $dateInput = $dateInput ?? '[name=invoice_date]';
    $formats = $numberFormats ?? [];
    $selected = old('document_series_id', $selectedFormatId ?? ($formats[0]['id'] ?? null));
    $preview = old('_number_preview');
    if (! $preview) {
        foreach ($formats as $f) {
            if ((string) $f['id'] === (string) $selected) {
                $preview = $f['preview'] ?? '';
                break;
            }
        }
        $preview = $preview ?: ($formats[0]['preview'] ?? '');
    }
@endphp

<div class="md:col-span-2 grid gap-4 sm:grid-cols-2"
     data-voucher-number-root
     data-voucher-type="{{ $docType }}"
     data-date-input="{{ $dateInput }}"
     data-next-number-url="{{ route('document-numbers.peek') }}">
    <div>
        <label class="label" for="f-document_series_id">{{ __('Number series') }}</label>
        <select id="f-document_series_id" name="document_series_id" class="input" required data-voucher-format>
            @forelse ($formats as $f)
                <option value="{{ $f['id'] }}" @selected((string) $selected === (string) $f['id'])>
                    {{ $f['name'] }}{{ ! empty($f['prefix']) ? ' ('.$f['prefix'].')' : '' }}
                </option>
            @empty
                <option value="">{{ __('No series for this year — open Transaction Master') }}</option>
            @endforelse
        </select>
        @error('document_series_id') <p class="error-text">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="label" for="f-voucher_number_preview">{{ __('Next number') }}</label>
        <input id="f-voucher_number_preview" type="text" class="input bg-cream/40" readonly
               value="{{ $preview }}" data-voucher-number tabindex="-1">
        <p class="mt-1 text-xs text-ink-soft" data-voucher-number-hint>{{ __('Preview only. Number is reserved when you save.') }}</p>
    </div>
</div>
