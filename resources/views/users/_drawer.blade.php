<form method="POST"
      action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}"
      data-ajax-form
      class="space-y-5">
    @csrf
    @if ($user->exists) @method('PUT') @endif

    <div data-form-errors class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" hidden></div>

    <div class="form-callout" role="note">
        <p class="form-callout__title">{{ __('Staff user') }}</p>
        <p class="mb-0">{{ __('Primary role sets menus. Optional overrides below change access for this person only.') }}</p>
    </div>

    <div class="drawer-section">
        <div class="drawer-section__head">
            <span class="drawer-section__ico"><x-icon name="user" class="h-4 w-4" /></span>
            <div>
                <strong class="text-leaf-dark">{{ __('Identity') }}</strong>
                <p class="text-xs text-ink-soft">{{ __('Name and email used to sign in.') }}</p>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" :label="__('Name')" :value="$user->name" required />
            <x-field name="email" type="email" :label="__('Email')" :value="$user->email" required autocomplete="off" />
            <x-field name="phone" :label="__('Phone')" :value="$user->phone" />
            <x-select-field name="role_id" :label="__('Primary role')" :value="$user->role_id" required :placeholder="__('Select role')"
                :options="$roles->mapWithKeys(fn ($r) => [$r->id => $r->display_name])->all()" />
            @unless ($user->exists)
                <div x-data="{ show: false }">
                    <label class="label" for="password">{{ __('Password') }} <span class="text-accent">*</span></label>
                    <div class="password-wrap">
                        <input id="password" name="password" :type="show ? 'text' : 'password'" required autocomplete="new-password" class="input pr-12" />
                        <button type="button" class="toggle-pass" @click="show = !show" :aria-label="show ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                        </button>
                    </div>
                </div>
                <x-field name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" />
            @else
                <p class="sm:col-span-2 text-xs text-ink-soft">{{ __('Use the Password action on the list to set a new password. It cannot be viewed.') }}</p>
            @endunless
            <x-select-field name="locale" :label="__('Language')" :value="$user->locale ?? 'en'" :options="['en' => 'English', 'mr' => 'मराठी']" />
            <div class="flex items-end">
                <x-check name="is_active" :label="__('Active (can sign in)')" :checked="$user->exists ? $user->is_active : true" />
            </div>
        </div>
    </div>

    <div class="drawer-section">
        <div class="drawer-section__head">
            <span class="drawer-section__ico"><x-icon name="building" class="h-4 w-4" /></span>
            <div>
                <strong class="text-leaf-dark">{{ __('Places / Godowns') }}</strong>
                <p class="text-xs text-ink-soft">{{ __('Godowns this user can work in') }}</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-4">
            @foreach ($godowns as $g)
                <label class="inline-flex items-center gap-2 text-sm font-semibold">
                    <input type="checkbox" name="godown_ids[]" value="{{ $g->id }}" class="checkbox"
                           @checked(in_array($g->id, old('godown_ids', $selectedGodowns)))>
                    {{ $g->display_name }}
                </label>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-ink-soft">{{ __('Owners can always access every godown.') }}</p>
    </div>

    <div class="drawer-section !bg-white">
        <div class="drawer-section__head">
            <span class="drawer-section__ico"><x-icon name="shield" class="h-4 w-4" /></span>
            <div>
                <strong class="text-leaf-dark">{{ __('Permission overrides') }}</strong>
                <p class="text-xs text-ink-soft">{{ __('Inherit = use the role permission. Allow / Deny overrides it for this user only.') }}</p>
            </div>
        </div>
        <div class="overflow-x-auto rounded-xl border border-sage-dark/60">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Menu') }}</th>
                        @foreach (['view' => 'View', 'add' => 'Add', 'edit' => 'Edit', 'delete' => 'Delete'] as $k => $l)
                            <th>{{ __($l) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($menuGroups as $group => $menus)
                        <tr class="bg-sage/50">
                            <td colspan="5" class="!py-2 text-[11px] font-bold uppercase tracking-wider text-leaf-dark">{{ $group }}</td>
                        </tr>
                        @foreach ($menus as $m)
                            @php($ov = $overrides[$m->id] ?? null)
                            <tr>
                                <td class="font-semibold">{{ $m->label }}</td>
                                @foreach (['view', 'add', 'edit', 'delete'] as $a)
                                    @php($val = old("ovr.{$m->id}.{$a}", $ov === null || $ov->{'can_'.$a} === null ? '' : (int) $ov->{'can_'.$a}))
                                    <td>
                                        <select name="ovr[{{ $m->id }}][{{ $a }}]" class="input input-sm w-24">
                                            <option value="" @selected((string) $val === '')>{{ __('Inherit') }}</option>
                                            <option value="1" @selected((string) $val === '1')>{{ __('Allow') }}</option>
                                            <option value="0" @selected((string) $val === '0')>{{ __('Deny') }}</option>
                                        </select>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 border-t border-sage pt-4">
        <button type="submit" class="btn-primary">{{ __('Save') }}</button>
        <button type="button" class="btn-secondary" onclick="window.Alpine && Alpine.store('ui').closeDrawer()">{{ __('Cancel') }}</button>
    </div>
</form>
