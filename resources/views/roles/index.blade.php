<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Roles & Permissions') }}</h1>
            <p class="text-xs text-ink-soft">{{ __('Allocate menu View / Add / Edit / Delete per role — Owner always has full access.') }}</p>
        </div>
    </x-slot>

    <div class="form-callout mb-4" role="note">
        <p class="form-callout__title">{{ __('Role allocation') }}</p>
        <p class="mb-0">{{ __('Tick menus this role may open. Staff users inherit these grants unless you set a personal override.') }}</p>
    </div>

    <div class="filter-bar card card-body mb-4">
        <div class="filter-bar__fields">
            <p class="text-sm text-ink-soft mb-0">{{ __('Allocate menu View / Add / Edit / Delete per role.') }}</p>
        </div>
        <div class="filter-bar__actions">
            @can('menu', ['roles', 'add'])
                <button type="button" class="btn-primary btn-sm"
                        onclick="KrushiDrawer.open('{{ route('roles.create') }}', '{{ __('Add :item', ['item' => __('Role')]) }}', '52rem')">
                    <x-icon name="plus" class="h-4 w-4" /> <span class="hidden sm:inline">{{ __('Add') }}</span>
                </button>
            @endcan
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Description') }}</th>
                    <th class="num">{{ __('Users') }}</th>
                    <th class="num">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $r)
                    <tr>
                        <td class="font-semibold">
                            {{ $r->display_name }}
                            @if ($r->is_system)<span class="badge-gray ml-1">{{ __('System') }}</span>@endif
                        </td>
                        <td>{{ $r->description }}</td>
                        <td class="num">{{ $r->users_count }}</td>
                        <td class="num whitespace-nowrap">
                            @can('menu', ['roles', 'edit'])
                                <button type="button" class="btn-secondary btn-sm"
                                        onclick="KrushiDrawer.open('{{ route('roles.edit', $r) }}', '{{ $r->slug === 'owner' ? __('View :item', ['item' => __('Role')]) : __('Edit :item', ['item' => __('Role')]) }}', '52rem')">
                                    {{ $r->slug === 'owner' ? __('View') : __('Edit') }}
                                </button>
                            @endcan
                            @can('menu', ['roles', 'delete'])
                                @unless ($r->is_system)
                                    <form method="POST" action="{{ route('roles.destroy', $r) }}" class="inline" onsubmit="return confirm('{{ __('Delete this record?') }}')">
                                        @csrf @method('DELETE')
                                        <button class="btn-danger btn-sm">{{ __('Delete') }}</button>
                                    </form>
                                @endunless
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
