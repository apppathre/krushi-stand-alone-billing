<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Staff & Users') }}</h1>
            <p class="text-xs text-ink-soft">{{ __('Logins, roles, places and permission overrides — same control style as your other ERP apps.') }}</p>
        </div>
    </x-slot>

    <div class="form-callout mb-4" role="note">
        <p class="form-callout__title">{{ __('Staff access') }}</p>
        <p class="mb-0">{{ __('Primary role sets the menus. Place checkboxes control which godowns they can bill from. Use Set password or Active toggle without opening the full form.') }}</p>
    </div>

    <x-auto-filter>
        <x-field name="q" :label="__('Search')" :value="request('q')" :placeholder="__('Name, email, phone…')" class="w-full sm:w-64" />
        <x-select-field name="role_id" :label="__('Role')" :value="request('role_id')" :placeholder="__('All')"
            :options="$roles->mapWithKeys(fn ($r) => [$r->id => $r->display_name])->all()" class="w-full sm:w-44" />
        <x-slot:actions>
            @can('menu', ['users', 'add'])
                <button type="button" class="btn-primary btn-sm"
                        onclick="KrushiDrawer.open('{{ route('users.create') }}', '{{ __('Add staff user') }}', '52rem')">
                    <x-icon name="plus" class="h-4 w-4" /> <span class="hidden sm:inline">{{ __('Add') }}</span>
                </button>
            @endcan
        </x-slot:actions>
    </x-auto-filter>

    <div id="list-results">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Places') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="num">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $u)
                        <tr>
                            <td class="font-semibold">{{ $u->name }}<span class="block text-xs font-normal text-ink-soft">{{ $u->phone }}</span></td>
                            <td>{{ $u->email }}</td>
                            <td><span class="badge-green">{{ $u->role?->display_name ?? '—' }}</span></td>
                            <td class="text-sm">{{ $u->role?->slug === 'owner' ? __('All') : ($u->godowns->map->display_name->implode(', ') ?: '—') }}</td>
                            <td><span class="{{ $u->is_active ? 'badge-green' : 'badge-gray' }}">{{ $u->is_active ? __('Active') : __('Inactive') }}</span></td>
                            <td class="num whitespace-nowrap">
                                @can('menu', ['users', 'edit'])
                                    <button type="button" class="btn-secondary btn-sm"
                                            onclick="KrushiDrawer.open('{{ route('users.edit', $u) }}', '{{ __('Edit staff user') }}', '52rem')">
                                        {{ __('Edit') }}
                                    </button>
                                    <button type="button" class="btn-secondary btn-sm"
                                            onclick="KrushiDrawer.open('{{ route('users.password', $u) }}', '{{ __('Set password') }}', '28rem')"
                                            title="{{ __('Set a new password (current password cannot be viewed)') }}">
                                        {{ __('Password') }}
                                    </button>
                                    @if ($u->id !== auth()->id())
                                        <form method="POST" action="{{ route('users.toggle', $u) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="btn-secondary btn-sm"
                                                    title="{{ $u->is_active ? __('Disable login') : __('Enable login') }}">
                                                {{ $u->is_active ? __('Off') : __('On') }}
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                                @can('menu', ['users', 'delete'])
                                    @if ($u->id !== auth()->id())
                                        <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline" onsubmit="return confirm('{{ __('Delete this record?') }}')">
                                            @csrf @method('DELETE')
                                            <button class="btn-danger btn-sm">{{ __('Delete') }}</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </div>
</x-app-layout>
