<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Bulk item entry') }}</h1>
            <p class="text-xs text-ink-soft">{{ __('Add many rows like Excel. Paste tab-separated columns from a spreadsheet, or type and add rows.') }}</p>
        </div>
    </x-slot>

    <div class="form-callout mb-4" role="note">
        <p class="form-callout__title">{{ __('Columns (paste order)') }}</p>
        <p class="mb-0 text-xs">
            <code>code | name | name_mr | category | mfr_code | unit | packing | marketed_by | usage | hsn | gst | purchase | sale | mrp | reorder | batch(Y/N)</code>
        </p>
        <p class="mt-1 mb-0 text-xs text-ink-soft">{{ __('Category / unit accept name or id. Manufacturer accepts short code. Required: name, category, unit, GST %.') }}</p>
    </div>

    <form method="POST" action="{{ route('products.bulk.store') }}"
          x-data="bulkItems({
              categories: @js($categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->display_name])->values()),
              manufacturers: @js($manufacturers->map(fn ($m) => ['id' => $m->id, 'name' => $m->display_name, 'code' => $m->short_code])->values()),
              units: @js($units->map(fn ($u) => ['id' => $u->id, 'name' => $u->short_name.' — '.$u->display_name, 'short' => $u->short_name])->values()),
              gstSlabs: @js($gstSlabs),
          })"
          class="space-y-4">
        @csrf

        <div class="filter-bar card card-body">
            <div class="filter-bar__fields flex-wrap gap-2">
                <button type="button" class="btn-secondary btn-sm" @click="add()">{{ __('Add row') }}</button>
                <button type="button" class="btn-secondary btn-sm" @click="add(5)">{{ __('+5 rows') }}</button>
                <label class="btn-secondary btn-sm cursor-pointer">
                    {{ __('Paste from Excel') }}
                    <textarea class="sr-only" @paste.prevent="paste($event)" rows="1"></textarea>
                </label>
                <button type="button" class="btn-secondary btn-sm" @click="focusPaste()">{{ __('Open paste box') }}</button>
            </div>
            <div class="filter-bar__actions">
                <a href="{{ route('products.index') }}" class="btn-secondary btn-sm">{{ __('Back') }}</a>
                <button type="submit" class="btn-primary btn-sm">{{ __('Save all') }}</button>
            </div>
        </div>

        <div x-show="pasteOpen" x-cloak class="card card-body">
            <label class="label">{{ __('Paste tab-separated rows here, then Apply') }}</label>
            <textarea x-ref="pasteBox" x-model="pasteText" class="input font-mono text-xs" rows="6"
                      placeholder="SD010&#9;Cotton Seed&#9;&#9;Seed&#9;MHY&#9;Pkt&#9;450g&#9;&#9;&#9;1207&#9;0&#9;780&#9;850&#9;864&#9;20&#9;Y"></textarea>
            <div class="mt-2 flex gap-2">
                <button type="button" class="btn-primary btn-sm" @click="applyPaste()">{{ __('Apply paste') }}</button>
                <button type="button" class="btn-secondary btn-sm" @click="pasteOpen = false">{{ __('Close') }}</button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                <ul class="list-disc pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="table-wrap overflow-x-auto">
            <table class="table text-xs">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('Code') }}</th>
                        <th class="min-w-[10rem]">{{ __('Name') }} *</th>
                        <th>{{ __('Name (MR)') }}</th>
                        <th class="min-w-[8rem]">{{ __('Category') }} *</th>
                        <th>{{ __('Mfr code') }}</th>
                        <th class="min-w-[7rem]">{{ __('Unit') }} *</th>
                        <th>{{ __('Packing') }}</th>
                        <th>{{ __('Marketed by') }}</th>
                        <th>{{ __('Usage') }}</th>
                        <th>{{ __('HSN') }}</th>
                        <th>{{ __('GST %') }} *</th>
                        <th>{{ __('Pur') }}</th>
                        <th>{{ __('Sale') }}</th>
                        <th>{{ __('MRP') }}</th>
                        <th>{{ __('Reorder') }}</th>
                        <th>{{ __('Batch') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, i) in rows" :key="row.key">
                        <tr>
                            <td class="text-ink-soft" x-text="i + 1"></td>
                            <td><input :name="`rows[${i}][code]`" x-model="row.code" class="input input-sm w-20"></td>
                            <td><input :name="`rows[${i}][name]`" x-model="row.name" @input="onName(row)" class="input input-sm min-w-[9rem]"></td>
                            <td><input :name="`rows[${i}][name_mr]`" x-model="row.name_mr" @input="onNameMr(row)" class="input input-sm w-28" title="{{ __('Auto from English — edit anytime') }}"></td>
                            <td>
                                <select :name="`rows[${i}][category_id]`" x-model="row.category_id" class="input input-sm">
                                    <option value="">—</option>
                                    <template x-for="c in categories" :key="c.id">
                                        <option :value="c.id" x-text="c.name"></option>
                                    </template>
                                </select>
                            </td>
                            <td><input :name="`rows[${i}][manufacturer_code]`" x-model="row.manufacturer_code" class="input input-sm w-16" list="mfr-codes"></td>
                            <td>
                                <select :name="`rows[${i}][unit_id]`" x-model="row.unit_id" class="input input-sm">
                                    <option value="">—</option>
                                    <template x-for="u in units" :key="u.id">
                                        <option :value="u.id" x-text="u.name"></option>
                                    </template>
                                </select>
                            </td>
                            <td><input :name="`rows[${i}][packing]`" x-model="row.packing" class="input input-sm w-24"></td>
                            <td><input :name="`rows[${i}][marketed_by]`" x-model="row.marketed_by" class="input input-sm w-28"></td>
                            <td><input :name="`rows[${i}][usage]`" x-model="row.usage" class="input input-sm w-28"></td>
                            <td><input :name="`rows[${i}][hsn_code]`" x-model="row.hsn_code" class="input input-sm w-16"></td>
                            <td>
                                <select :name="`rows[${i}][gst_rate]`" x-model="row.gst_rate" class="input input-sm w-16">
                                    <template x-for="g in gstSlabs" :key="g">
                                        <option :value="g" x-text="g"></option>
                                    </template>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" :name="`rows[${i}][purchase_rate]`" x-model="row.purchase_rate" class="input input-sm w-20"></td>
                            <td><input type="number" step="0.01" :name="`rows[${i}][sale_rate]`" x-model="row.sale_rate" class="input input-sm w-20"></td>
                            <td><input type="number" step="0.01" :name="`rows[${i}][mrp]`" x-model="row.mrp" class="input input-sm w-20"></td>
                            <td><input type="number" step="0.001" :name="`rows[${i}][reorder_level]`" x-model="row.reorder_level" class="input input-sm w-16"></td>
                            <td>
                                <select :name="`rows[${i}][batch_required]`" x-model="row.batch_required" class="input input-sm w-14">
                                    <option value="1">Y</option>
                                    <option value="0">N</option>
                                </select>
                            </td>
                            <td><button type="button" class="btn-danger btn-sm" @click="remove(i)">&times;</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <datalist id="mfr-codes">
            @foreach ($manufacturers->whereNotNull('short_code') as $m)
                <option value="{{ $m->short_code }}">{{ $m->display_name }}</option>
            @endforeach
        </datalist>
    </form>

</x-app-layout>
