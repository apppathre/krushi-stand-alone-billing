<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Menus') }}</h1>
    </x-slot>

    <p class="mb-4 max-w-3xl text-sm text-ink-soft">{{ __('Rename, reorder or hide sidebar menus. Hidden menus are removed for everyone. Grant access per role under Roles & Permissions.') }}</p>

    <div class="card mb-4 max-w-4xl" x-data="{
        mode: localStorage.getItem('krushi.nav.mode') || 'accordion',
        setMode(m) {
            this.mode = m;
            localStorage.setItem('krushi.nav.mode', m);
            window.dispatchEvent(new CustomEvent('krushi:nav-mode', { detail: m }));
        }
    }">
        <div class="card-body space-y-3">
            <div>
                <h2 class="card-title text-base">{{ __('Sidebar menu control') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Choose whether only the selected parent stays open, or several groups can stay expanded.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-sm" :class="mode === 'accordion' ? 'btn-primary' : 'btn-secondary'" @click="setMode('accordion')">
                    {{ __('Accordion — only selected parent') }}
                </button>
                <button type="button" class="btn-sm" :class="mode === 'remember' ? 'btn-primary' : 'btn-secondary'" @click="setMode('remember')">
                    {{ __('Remember — keep groups open') }}
                </button>
            </div>
        </div>
    </div>

    <div class="table-wrap max-w-4xl">
        <table class="table">
            <thead><tr><th>{{ __('Menu') }}</th><th>{{ __('Marathi label') }}</th><th>{{ __('Route') }}</th><th class="num">{{ __('Order') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($menus as $group)
                    @php
                        $flat = collect([[$group, 0]]);
                        foreach ($group->children as $child) {
                            $flat->push([$child, 1]);
                            foreach ($child->children as $leaf) {
                                $flat->push([$leaf, 2]);
                            }
                        }
                    @endphp
                    @foreach ($flat as [$m, $depth])
                        <tr class="{{ $depth === 0 ? 'bg-sage/40 font-bold' : ($depth === 1 && ! $m->route_name ? 'bg-sage/20 font-semibold' : '') }}">
                            <td class="{{ $depth === 1 ? 'pl-8' : ($depth >= 2 ? 'pl-14' : '') }}">{{ $m->label_en }}</td>
                            <td>{{ $m->label_mr }}</td>
                            <td class="text-xs text-ink-soft">{{ $m->route_name ?? __('(group)') }}</td>
                            <td class="num">{{ $m->sort_order }}</td>
                            <td><span class="{{ $m->is_active ? 'badge-green' : 'badge-gray' }}">{{ $m->is_active ? __('Active') : __('Hidden') }}</span></td>
                            <td class="num">
                                @can('menu', ['menus', 'edit'])
                                    <button type="button" class="btn-secondary btn-sm"
                                            onclick="KrushiDrawer.open('{{ route('menus.edit', $m) }}', '{{ __('Edit :item', ['item' => __('Menu')]) }}: {{ addslashes($m->label_en) }}', '28rem')">
                                        {{ __('Edit') }}
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
