<form method="POST"
      action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}"
      data-ajax-form
      class="space-y-4"
      x-data="{
          hsn: @js(old('hsn_code', $product->hsn_code)),
          gst: @js((string) old('gst_rate', qty($product->gst_rate ?? 0))),
          hsnMap: @js($hsnCodes->mapWithKeys(fn ($h) => [$h->code => (string) qty($h->gst_rate)])->all()),
          applyHsn() {
              if (this.hsn && this.hsnMap[this.hsn] !== undefined) {
                  this.gst = this.hsnMap[this.hsn];
              }
          }
      }">
    @csrf
    @if ($product->exists) @method('PUT') @endif

    <div data-form-errors class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" hidden></div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="name" :label="__('Name (English)')" :value="$product->name" required
                 :hint="__('Marathi fills automatically as you type — you can still edit it.')" />
        <x-field name="name_mr" :label="__('Name (Marathi)')" :value="$product->name_mr"
                 :hint="__('Auto from English. Clear this box to resume auto-fill.')" />
        <x-field name="code" :label="__('Product code')" :value="$product->code" :hint="__('Leave blank to auto-generate.')" />

        <div>
            <label for="f-hsn_code" class="label">{{ __('HSN / SAC') }}</label>
            <select id="f-hsn_code" name="hsn_code" class="input" x-model="hsn" @change="applyHsn()">
                <option value="">{{ __('Select HSN / SAC') }}</option>
                @foreach ($hsnCodes as $h)
                    <option value="{{ $h->code }}">{{ $h->code }} — {{ $h->display_name }} ({{ qty($h->gst_rate) }}%)</option>
                @endforeach
            </select>
            @error('hsn_code') <p class="error-text">{{ $message }}</p> @enderror
        </div>

        <x-select-field name="category_id" :label="__('Category')" :value="$product->category_id" required :placeholder="__('Select category')"
            :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->display_name])->all()" />
        <x-select-field name="manufacturer_id" :label="__('Manufacturer')" :value="$product->manufacturer_id" :placeholder="__('None')"
            :options="$manufacturers->mapWithKeys(fn ($m) => [$m->id => $m->display_name])->all()" />
        <x-select-field name="unit_id" :label="__('Unit')" :value="$product->unit_id" required :placeholder="__('Select unit')"
            :options="$units->mapWithKeys(fn ($u) => [$u->id => $u->display_name.' ('.$u->short_name.')'])->all()" />
        <x-field name="packing" :label="__('Packing')" :value="$product->packing"
                 :hint="__('e.g. 1 L bottle, 50 kg bag, 100 ml')" />
        <x-field name="marketed_by" :label="__('Marketed by')" :value="$product->marketed_by"
                 :hint="__('Brand / marketer on the pack')" />
        <x-field name="usage" :label="__('Usage / composition')" :value="$product->usage"
                 :hint="__('e.g. Insecticide for cotton, 17.8% SL')" class="sm:col-span-2" />

        <div>
            <label for="f-gst_rate" class="label">{{ __('GST slab') }} <span class="text-accent">*</span></label>
            <select id="f-gst_rate" name="gst_rate" class="input" required x-model="gst">
                @foreach ($gstSlabs as $s)
                    <option value="{{ qty($s->rate) }}">{{ $s->display_name ?? ('GST '.qty($s->rate).'%') }}</option>
                @endforeach
            </select>
            @error('gst_rate') <p class="error-text">{{ $message }}</p> @enderror
        </div>

        <x-field name="purchase_rate" type="number" step="0.01" min="0" :label="__('Purchase rate')" :value="$product->purchase_rate" />
        <x-field name="sale_rate" type="number" step="0.01" min="0" :label="__('Sale rate')" :value="$product->sale_rate" />
        <x-field name="mrp" type="number" step="0.01" min="0" :label="__('MRP')" :value="$product->mrp" />
        <x-field name="reorder_level" type="number" step="0.001" min="0" :label="__('Reorder level (low-stock alert)')" :value="$product->reorder_level" />
    </div>

    <div class="flex flex-wrap gap-6">
        <x-check name="batch_required" :label="__('Batch tracking required (seeds, pesticides…)')" :checked="$product->batch_required" />
        <x-check name="is_active" :label="__('Active')" :checked="$product->is_active" />
        <x-check name="show_on_live_stock" :label="__('Show on live stock board')"
                 :checked="old('show_on_live_stock', $product->exists ? $product->show_on_live_stock : true)" />
    </div>

    <div class="flex flex-wrap gap-2 border-t border-sage pt-4">
        <button type="submit" class="btn-primary">{{ __('Save') }}</button>
        <button type="button" class="btn-secondary" onclick="window.Alpine && Alpine.store('ui').closeDrawer()">{{ __('Cancel') }}</button>
    </div>
</form>
