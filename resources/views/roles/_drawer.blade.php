@php($isOwner = $role->slug === 'owner')

<form method="POST"
      action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}"
      data-ajax-form
      class="space-y-5"
      x-data="{
          toggleRow(e, id) { document.querySelectorAll(`#ui-drawer-body [data-row='${id}'] input[type=checkbox]`).forEach(c => { if (!c.disabled) c.checked = e.target.checked }) },
          toggleCol(e, action) { document.querySelectorAll(`#ui-drawer-body [data-col='${action}']`).forEach(c => { if (!c.disabled) c.checked = e.target.checked }) },
          selectAll(on) { document.querySelectorAll('#ui-drawer-body input[type=checkbox][data-col]').forEach(c => { if (!c.disabled) c.checked = on }) },
      }">
    @csrf
    @if ($role->exists) @method('PUT') @endif

    <div data-form-errors class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" hidden></div>

    <div class="form-callout" role="note">
        <p class="form-callout__title">{{ __('Shop role') }}</p>
        <p class="mb-0">{{ __('Allocate menus this role may open. Tick View, then optionally Add, Edit and Delete.') }}</p>
    </div>

    <div class="drawer-section">
        <div class="drawer-section__head">
            <span class="drawer-section__ico"><x-icon name="shield" class="h-4 w-4" /></span>
            <div>
                <strong class="text-leaf-dark">{{ __('Role details') }}</strong>
                <p class="text-xs text-ink-soft">{{ __('Name shown when assigning staff.') }}</p>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" :label="__('Name (English)')" :value="$role->name" required
                     :hint="__('Marathi fills automatically as you type — you can still edit it.')" />
            <x-field name="name_mr" :label="__('Name (Marathi)')" :value="$role->name_mr"
                     :hint="__('Auto from English. Clear this box to resume auto-fill.')" />
            <x-field name="description" :label="__('Description')" :value="$role->description" class="sm:col-span-2" />
        </div>
    </div>

    <div class="drawer-section !bg-white">
        <div class="drawer-section__head">
            <span class="drawer-section__ico"><x-icon name="menu" class="h-4 w-4" /></span>
            <div class="min-w-0 flex-1">
                <strong class="text-leaf-dark">{{ __('Menu allocation') }}</strong>
                @if ($isOwner)
                    <p class="text-xs font-semibold text-accent">{{ __('The Owner role always has full access and cannot be restricted.') }}</p>
                @else
                    <p class="text-xs text-ink-soft">{{ __('Grouped by sidebar section. Use Select all / Clear for quick edits.') }}</p>
                @endif
            </div>
            @unless ($isOwner)
                <div class="flex shrink-0 gap-2">
                    <button type="button" class="btn-secondary btn-sm" @click="selectAll(true)">{{ __('Select all') }}</button>
                    <button type="button" class="btn-secondary btn-sm" @click="selectAll(false)">{{ __('Clear') }}</button>
                </div>
            @endunless
        </div>

        <div class="overflow-x-auto rounded-xl border border-sage-dark/60">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Menu') }}</th>
                        @foreach (['view' => 'View', 'add' => 'Add', 'edit' => 'Edit', 'delete' => 'Delete'] as $a => $l)
                            <th class="text-center">
                                <label class="inline-flex items-center gap-1">
                                    <input type="checkbox" class="checkbox" @change="toggleCol($event, '{{ $a }}')" @disabled($isOwner)> {{ __($l) }}
                                </label>
                            </th>
                        @endforeach
                        <th class="text-center">{{ __('All') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($menuGroups as $group => $menus)
                        <tr class="bg-sage/50">
                            <td colspan="6" class="!py-2 text-[11px] font-bold uppercase tracking-wider text-leaf-dark">{{ $group }}</td>
                        </tr>
                        @foreach ($menus as $m)
                            @php($g = $grants[$m->id] ?? null)
                            <tr data-row="{{ $m->id }}">
                                <td class="font-semibold">{{ $m->label }}</td>
                                @foreach (['view', 'add', 'edit', 'delete'] as $a)
                                    <td class="text-center">
                                        <input type="checkbox" class="checkbox" data-col="{{ $a }}" name="perm[{{ $m->id }}][{{ $a }}]" value="1"
                                               @checked($isOwner || ($g && $g->{'can_'.$a})) @disabled($isOwner)>
                                    </td>
                                @endforeach
                                <td class="text-center"><input type="checkbox" class="checkbox" @change="toggleRow($event, {{ $m->id }})" @disabled($isOwner)></td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 border-t border-sage pt-4">
        @unless ($isOwner)<button type="submit" class="btn-primary">{{ __('Save') }}</button>@endunless
        <button type="button" class="btn-secondary" onclick="window.Alpine && Alpine.store('ui').closeDrawer()">{{ $isOwner ? __('Back') : __('Cancel') }}</button>
    </div>
</form>
