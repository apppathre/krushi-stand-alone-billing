<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Opening stock — bulk update') }}</h1>
            <p class="text-xs text-ink-soft">{{ __('Enter qty on items to post. Leave blank to skip. Batch required where marked.') }}</p>
        </div>
    </x-slot>

    <div class="mb-3 flex flex-wrap justify-end gap-2">
        <button type="button" class="btn-secondary btn-sm"
                onclick="KrushiDrawer.open('{{ route('opening-stock.create', ['drawer' => 1, 'godown_id' => $placeId]) }}', '{{ __('Add opening stock') }}', '36rem')">
            <x-icon name="plus" class="h-4 w-4" /> {{ __('Add one (drawer)') }}
        </button>
        <a href="{{ route('opening-stock.index') }}" class="btn-secondary btn-sm">{{ __('Back') }}</a>
    </div>

    <form method="POST" action="{{ route('opening-stock.store') }}"
          x-data="openingBulk({
              products: @js($products),
              placeId: {{ (int) $placeId }},
          })"
          class="space-y-4"
          data-busy-label="{{ __('Saving…') }}">
        @csrf

        <div class="card card-body grid gap-4 md:grid-cols-4">
            <div>
                <label class="label" for="f-godown_id">{{ __('Place / Godown') }} <span class="text-accent">*</span></label>
                <select id="f-godown_id" name="godown_id" class="input" required x-model="placeId"
                        @change="window.location='{{ route('opening-stock.bulk') }}?godown_id='+placeId">
                    @foreach ($places as $g)
                        <option value="{{ $g->id }}" @selected((int) $placeId === (int) $g->id)>{{ $g->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <x-field name="movement_date" type="date" :label="__('As on date')"
                     :value="old('movement_date', $voucherDate ?? now()->toDateString())"
                     :min="optional($currentFinancialYear ?? null)?->start_date?->toDateString()"
                     :max="optional($currentFinancialYear ?? null)?->end_date?->toDateString()"
                     required />
            <div class="md:col-span-2 flex flex-wrap items-end gap-2">
                <div class="min-w-[12rem] flex-1">
                    <label class="label" for="os-q">{{ __('Filter items') }}</label>
                    <input id="os-q" type="search" class="input" x-model="q" placeholder="{{ __('Code / name…') }}" autocomplete="off">
                </div>
                <label class="inline-flex items-center gap-2 pb-2 text-sm font-semibold">
                    <input type="checkbox" class="checkbox" x-model="onlyQty">
                    {{ __('With qty only') }}
                </label>
                <button type="button" class="btn-secondary btn-sm mb-0.5" @click="fillRates()">{{ __('Fill blank rates') }}</button>
            </div>
        </div>

        <div class="rounded-lg border border-sage/60 bg-cream/40 px-3 py-2 text-sm" x-text="metaLabel()"></div>

        @error('items') <p class="error-text">{{ $message }}</p> @enderror
        @if ($errors->any() && ! $errors->has('items'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                <ul class="list-disc pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="table-wrap max-h-[70vh] overflow-auto">
            <table class="table text-sm">
                <thead class="sticky top-0 z-10 bg-white">
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th class="min-w-[12rem]">{{ __('Item') }}</th>
                        <th>{{ __('Unit') }}</th>
                        <th class="num">{{ __('Qty') }}</th>
                        <th>{{ __('Batch') }}</th>
                        <th>{{ __('Mfg') }}</th>
                        <th>{{ __('Expiry') }}</th>
                        <th class="num">{{ __('Rate') }}</th>
                        <th class="num">{{ __('MRP') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="p in products" :key="p.id">
                        <tr x-show="visible(p)" x-cloak>
                            <td class="font-mono text-xs text-ink-soft" x-text="p.code || '—'"></td>
                            <td>
                                <span class="font-semibold" x-text="p.name"></span>
                                <template x-if="p.batch_required">
                                    <span class="ml-1 text-[10px] font-bold uppercase text-accent">{{ __('Batch') }}</span>
                                </template>
                                <input type="hidden" :name="`items[${p.id}][product_id]`" :value="p.id">
                            </td>
                            <td class="text-xs" x-text="p.unit || '—'"></td>
                            <td>
                                <input type="number" step="0.001" min="0" class="input input-sm w-24 text-right"
                                       :name="`items[${p.id}][qty]`" x-model="p.qty" inputmode="decimal">
                            </td>
                            <td>
                                <input type="text" class="input input-sm w-28" :name="`items[${p.id}][batch_no]`" x-model="p.batch_no"
                                       :disabled="!p.batch_required" :required="p.batch_required && Number(p.qty) > 0"
                                       :placeholder="p.batch_required ? '{{ __('Batch no') }}' : 'NA'">
                            </td>
                            <td>
                                <input type="date" class="input input-sm w-36" :name="`items[${p.id}][mfg_date]`" x-model="p.mfg_date"
                                       :disabled="!p.batch_required">
                            </td>
                            <td>
                                <input type="date" class="input input-sm w-36" :name="`items[${p.id}][expiry_date]`" x-model="p.expiry_date"
                                       :disabled="!p.batch_required">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" class="input input-sm w-28 text-right"
                                       :name="`items[${p.id}][rate]`" x-model="p.rate" inputmode="decimal">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" class="input input-sm w-28 text-right"
                                       :name="`items[${p.id}][mrp]`" x-model="p.mrp" inputmode="decimal">
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="btn-primary">{{ __('Save opening stock') }}</button>
            <a href="{{ route('opening-stock.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-app-layout>
