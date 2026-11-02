@php
    $helpTopic = match ($type) {
        'transfer' => 'stock_transfer',
        'rejection' => 'stock_rejection',
        'issue' => 'stock_issue',
        'physical' => 'physical_stock',
        'return' => 'stock_return',
        default => 'stock_journal',
    };
    $menuKey = $menuKey ?? \App\Http\Controllers\StockJournalController::menuKey($type);
    $isEdit = isset($editing) && $editing;
    $formItems = old('items', $editItems ?? []);
@endphp
<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ $isEdit ? __('Edit') . ' — ' : '' }}{{ $title }}@if ($isEdit) {{ $editing->journal_no }}@endif</h1>
    </x-slot>

    <div class="mb-3 flex flex-wrap gap-1 overflow-x-auto pb-1 no-print">
        @foreach ($types as $key => [$en])
            @php $mk = \App\Http\Controllers\StockJournalController::menuKey($key); @endphp
            @if (auth()->user()->canMenu($mk, 'view'))
                <a href="{{ route('stock-journals.'.$key) }}"
                   class="btn-sm shrink-0 {{ $type === $key && ! $isEdit ? 'btn-primary' : 'btn-secondary' }}">{{ __($en) }}</a>
            @endif
        @endforeach
        <a href="{{ route('opening-stock.index') }}" class="btn-secondary btn-sm shrink-0">{{ __('Opening') }}</a>
    </div>

    <x-page-head class="mb-3" :title="$isEdit ? __('Edit').' '.$editing->journal_no : $title" :blurb="$blurb">
        <x-screen-help :topic="$helpTopic" />
        @if (! $isEdit)
            <button type="button" class="btn-secondary btn-sm"
                    onclick="KrushiDrawer.open('{{ route('stock-journals.'.$type.'.browse') }}', '{{ __('Recent') }} — {{ $title }}', '48rem')">
                <x-icon name="list" class="h-4 w-4" /> <span class="page-head__btn-label">{{ __('Recent') }}</span>
            </button>
        @else
            <a href="{{ route('stock-journals.show', $editing) }}" class="btn-secondary btn-sm">{{ __('Cancel edit') }}</a>
        @endif
    </x-page-head>

    @if ($isEdit || auth()->user()->canMenu($menuKey, 'add'))
        <section class="mb-2" aria-label="{{ $title }}">
            <form method="POST"
                  action="{{ $isEdit ? route('stock-journals.update', $editing) : route('stock-journals.'.$type.'.store') }}"
                  x-data="stockJournalForm({
                      type: '{{ $type }}',
                      products: @js($products),
                      catalogIn: @js($catalogIn),
                      places: @js($places->map(fn ($g) => ['id' => $g->id, 'name' => $g->display_name])->values()->all()),
                      defaultPlaceId: {{ (int) old('godown_id', $placeId) }},
                      old: @js($formItems),
                  })"
                  class="txn-desk space-y-4" data-busy-label="{{ __('Saving…') }}">
                @csrf
                @if ($isEdit) @method('PUT') @endif

                <div class="card card-body grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="label" for="f-godown_id">{{ $type === 'transfer' ? __('From place') : __('Place') }} <span class="text-accent">*</span></label>
                        <select id="f-godown_id" name="godown_id" class="input" required
                                x-model="defaultPlaceId" @change="setDefaultPlace($event.target.value)">
                            @foreach ($places as $g)
                                <option value="{{ $g->id }}" @selected((int) old('godown_id', $placeId) === $g->id)>{{ $g->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($type === 'transfer')
                        <div>
                            <label class="label" for="f-to_godown_id">{{ __('To place') }} <span class="text-accent">*</span></label>
                            <select id="f-to_godown_id" name="to_godown_id" class="input" required>
                                <option value="">{{ __('Select') }}</option>
                                @foreach ($places as $g)
                                    <option value="{{ $g->id }}" @selected((int) old('to_godown_id', $toPlaceId) === $g->id)>{{ $g->display_name }}</option>
                                @endforeach
                            </select>
                            @error('to_godown_id') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <x-field name="journal_date" type="date" :label="__('Date')"
                             :value="old('journal_date', $voucherDate ?? now()->toDateString())"
                             :min="optional($currentFinancialYear ?? null)?->start_date?->toDateString()"
                             :max="optional($currentFinancialYear ?? null)?->end_date?->toDateString()"
                             required />
                    @if (! $isEdit)
                        @include('partials.voucher-number', [
                            'docType' => $docType,
                            'dateInput' => '[name=journal_date]',
                            'numberFormats' => $numberFormats ?? [],
                        ])
                    @else
                        <div>
                            <label class="label">{{ __('Number') }}</label>
                            <input class="input bg-sage/30" value="{{ $editing->journal_no }}" readonly>
                        </div>
                    @endif
                    <x-field name="notes" :label="__('Narration')" :value="old('notes', $editing->notes ?? '')" class="md:col-span-2" />
                </div>

                @include('partials.txn-lines-journal')

                @foreach ($errors->get('items') as $m) <p class="error-text">{{ $m }}</p> @endforeach
                @foreach ($errors->get('items.*') as $messages) @foreach ($messages as $m) <p class="error-text">{{ $m }}</p> @endforeach @endforeach

                <div class="txn-sticky-actions flex flex-wrap gap-2">
                    <button type="button" class="btn-secondary w-full sm:w-auto" @click="add()"><x-icon name="plus" class="h-4 w-4" /> {{ __('Add line') }}</button>
                    <button type="submit" class="btn-primary flex-1 sm:flex-none">{{ $isEdit ? __('Update') : __('Save') }}</button>
                </div>
            </form>
        </section>
    @endif
</x-app-layout>
