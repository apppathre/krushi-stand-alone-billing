@php
    $companyName = isset($shellCompany) ? $shellCompany->display_name : config('app.name');
    $tagline = isset($shellCompany) && $shellCompany->tagline ? $shellCompany->tagline : __('Krushi Seva');
    $menuIndex = collect($shellMenus ?? [])->flatMap(function ($menu) {
        if ($menu->route_name) {
            return [[
                'label' => $menu->label,
                'label_mr' => $menu->label_mr,
                'group' => '',
                'url' => route($menu->route_name),
                'icon' => $menu->icon ?? 'tag',
            ]];
        }

        return $menu->children->flatMap(function ($c) use ($menu) {
            if ($c->route_name) {
                return [[
                    'label' => $c->label,
                    'label_mr' => $c->label_mr,
                    'group' => $menu->label,
                    'url' => route($c->route_name),
                    'icon' => $c->icon ?? 'tag',
                ]];
            }

            return $c->children->map(fn ($leaf) => [
                'label' => $leaf->label,
                'label_mr' => $leaf->label_mr,
                'group' => $menu->label.' · '.$c->label,
                'url' => route($leaf->route_name),
                'icon' => $leaf->icon ?? 'tag',
            ]);
        });
    })->values();

    $routeMatches = function (?string $routeName): bool {
        if (! $routeName) {
            return false;
        }

        return request()->routeIs(str_ends_with($routeName, '.index')
            ? \Illuminate\Support\Str::beforeLast($routeName, '.index').'.*'
            : $routeName.'*');
    };

    $activeGroupKey = '';
    $activeNestedKey = '';
    foreach ($shellMenus ?? [] as $menu) {
        if ($menu->route_name || $menu->children->isEmpty()) {
            continue;
        }
        $groupKey = $menu->key ?: \Illuminate\Support\Str::slug($menu->label_en ?? $menu->label);
        foreach ($menu->children as $c) {
            if ($c->route_name && $routeMatches($c->route_name)) {
                $activeGroupKey = $groupKey;
                break 2;
            }
            if (! $c->route_name) {
                $nestedKey = $c->key ?: \Illuminate\Support\Str::slug($c->label_en ?? $c->label);
                foreach ($c->children as $leaf) {
                    if ($routeMatches($leaf->route_name)) {
                        $activeGroupKey = $groupKey;
                        $activeNestedKey = $nestedKey;
                        break 3;
                    }
                }
            }
        }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $companyName }}</title>
    @include('layouts.partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased" x-data="portalShell({{ Js::from($menuIndex) }}, {{ Js::from($activeGroupKey) }}, {{ Js::from($activeNestedKey) }})">
<div class="app-shell has-sidebar" id="app-shell"
     :class="{ 'is-mini': mini, 'nav-open': navOpen }"
     @resize.window="if (window.innerWidth < 1024) { mini = false; }">

    {{-- Sidebar (TMS pattern) --}}
    <aside class="app-sidebar" id="app-sidebar" aria-label="{{ __('Menu') }}">
        <div class="app-sidebar-brand">
            <a href="{{ route('dashboard') }}" class="app-sidebar-brand-link" @click="navOpen = false">
                <span class="app-sidebar-logo">
                    <x-application-logo class="h-7 w-7" />
                </span>
                <span class="app-sidebar-brand-text">
                    <strong>{{ $companyName }}</strong>
                    <span>{{ $tagline }}</span>
                </span>
            </a>
            <button type="button" class="sidebar-mini-btn" @click="toggleMini()"
                    :title="mini ? '{{ __('Expand menu') }}' : '{{ __('Collapse menu') }}'"
                    :aria-label="mini ? '{{ __('Expand menu') }}' : '{{ __('Collapse menu') }}'">
                <svg class="h-4 w-4 transition" :class="{ 'rotate-180': mini }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <button type="button" class="sidebar-close-btn" @click="navOpen = false" aria-label="{{ __('Close') }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <nav class="side-nav" aria-label="{{ __('Menus') }}">
            @foreach (($shellMenus ?? collect()) as $menu)
                @if ($menu->route_name)
                    @php
                        $active = request()->routeIs(str_ends_with($menu->route_name, '.index')
                            ? \Illuminate\Support\Str::beforeLast($menu->route_name, '.index').'.*'
                            : $menu->route_name.'*');
                    @endphp
                    <a href="{{ route($menu->route_name) }}"
                       class="side-link {{ $active ? 'is-active' : '' }}"
                       @click="navOpen = false"
                       :title="mini ? '{{ $menu->label }}' : ''">
                        <span class="side-ico"><x-icon :name="$menu->icon ?? 'tag'" class="h-3.5 w-3.5" /></span>
                        <span class="side-label">{{ $menu->label }}</span>
                    </a>
                @else
                    @php
                        $groupKey = $menu->key ?: \Illuminate\Support\Str::slug($menu->label_en ?? $menu->label);
                        $groupActive = $menu->children->contains(function ($c) use ($routeMatches) {
                            if ($c->route_name) {
                                return $routeMatches($c->route_name);
                            }

                            return $c->children->contains(fn ($leaf) => $routeMatches($leaf->route_name));
                        });
                    @endphp
                    <div class="side-group" data-group-key="{{ $groupKey }}"
                         :class="{ 'is-open': isGroupOpen('{{ $groupKey }}') }">
                        <button type="button"
                                class="side-group-toggle {{ $groupActive ? 'is-active' : '' }}"
                                @click="toggleGroup('{{ $groupKey }}')"
                                :aria-expanded="isGroupOpen('{{ $groupKey }}') ? 'true' : 'false'"
                                :title="mini ? '{{ $menu->label }}' : ''">
                            <span class="side-ico"><x-icon :name="$menu->icon ?? 'tag'" class="h-3.5 w-3.5" /></span>
                            <span class="side-label">{{ $menu->label }}</span>
                            <svg class="side-caret h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="side-sub">
                            @foreach ($menu->children as $child)
                                @if ($child->route_name)
                                    @php $active = $routeMatches($child->route_name); @endphp
                                    <a href="{{ route($child->route_name) }}"
                                       class="side-link {{ $active ? 'is-active' : '' }}"
                                       @click="navOpen = false"
                                       :title="mini ? '{{ $child->label }}' : ''">
                                        <span class="side-ico"><x-icon :name="$child->icon ?? 'tag'" class="h-3.5 w-3.5" /></span>
                                        <span class="side-label">{{ $child->label }}</span>
                                    </a>
                                @else
                                    @php
                                        $nestedKey = $child->key ?: \Illuminate\Support\Str::slug($child->label_en ?? $child->label);
                                        $nestedActive = $child->children->contains(fn ($leaf) => $routeMatches($leaf->route_name));
                                    @endphp
                                    <div class="side-group side-group--nested" data-group-key="{{ $nestedKey }}"
                                         :class="{ 'is-open': isGroupOpen('{{ $nestedKey }}') }">
                                        <button type="button"
                                                class="side-group-toggle side-group-toggle--nested {{ $nestedActive ? 'is-active' : '' }}"
                                                @click="toggleGroup('{{ $nestedKey }}', '{{ $groupKey }}')"
                                                :aria-expanded="isGroupOpen('{{ $nestedKey }}') ? 'true' : 'false'"
                                                :title="mini ? '{{ $child->label }}' : ''">
                                            <span class="side-ico"><x-icon :name="$child->icon ?? 'tag'" class="h-3.5 w-3.5" /></span>
                                            <span class="side-label">{{ $child->label }}</span>
                                            <svg class="side-caret h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div class="side-sub side-sub--nested">
                                            @foreach ($child->children as $leaf)
                                                @php $active = $routeMatches($leaf->route_name); @endphp
                                                <a href="{{ route($leaf->route_name) }}"
                                                   class="side-link side-link--nested {{ $active ? 'is-active' : '' }}"
                                                   @click="navOpen = false"
                                                   :title="mini ? '{{ $leaf->label }}' : ''">
                                                    <span class="side-ico"><x-icon :name="$leaf->icon ?? 'tag'" class="h-3.5 w-3.5" /></span>
                                                    <span class="side-label">{{ $leaf->label }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>

        <div class="side-foot">
            <a href="{{ route('profile.edit') }}" class="side-link" @click="navOpen = false" :title="mini ? '{{ __('My Profile') }}' : ''">
                <span class="side-ico"><x-icon name="user" class="h-3.5 w-3.5" /></span>
                <span class="side-label">{{ __('My Profile') }}</span>
            </a>
            <form method="POST" action="{{ route('logout') }}" data-busy-label="{{ __('Signing out…') }}">
                @csrf
                <button type="submit" class="side-logout" data-busy-label="{{ __('Signing out…') }}" :title="mini ? '{{ __('Log Out') }}' : ''">
                    <span class="side-ico">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </span>
                    <span class="side-label">{{ __('Log Out') }}</span>
                </button>
            </form>
        </div>
    </aside>

    <button type="button" class="shell-nav-backdrop" x-show="navOpen" x-cloak @click="navOpen = false" aria-hidden="true"></button>

    <div class="app-content">
        <header class="page-topbar" id="page-topbar">
            <div class="page-topbar__left">
                <button type="button" class="topbar-icon-btn mobile-nav-btn" @click="navOpen = true" aria-label="{{ __('Menu') }}">
                    <x-icon name="menu" class="h-5 w-5" />
                </button>
                <div class="page-topbar__titles">
                    @isset($header)
                        {{ $header }}
                    @else
                        <h1 class="page-title !text-xl sm:!text-[1.25rem]">{{ $companyName }}</h1>
                    @endisset
                </div>
            </div>

            <div class="page-topbar__center">
                <div class="topbar-search-wrap">
                    <div class="topbar-search" @click.outside="searchOpen = false">
                        <div class="topbar-search__shell">
                            <span class="topbar-search__icon" aria-hidden="true">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                            </span>
                            <input type="search" class="topbar-search__input" x-ref="menuSearch"
                                   x-model="searchQ" @focus="searchOpen = true"
                                   placeholder="{{ __('Search menus…') }}" autocomplete="off" aria-label="{{ __('Search menus…') }}" />
                            <span class="topbar-search__hint">Ctrl + K</span>
                        </div>
                        <div class="topbar-search__results" x-show="searchOpen" x-cloak>
                            <template x-for="item in filteredMenus" :key="item.url">
                                <button type="button" class="menu-search-item" @click="goMenu(item.url)">
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold text-ink" x-text="item.label"></span>
                                        <span class="block text-xs text-ink-soft" x-text="item.group" x-show="item.group"></span>
                                    </span>
                                </button>
                            </template>
                            <p class="px-4 py-5 text-center text-sm text-ink-soft" x-show="filteredMenus.length === 0">{{ __('No menus found.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-topbar__right">
                @if (isset($currentFinancialYear))
                    @php
                        $fyTitle = __('Active financial year').': '.fmt_date($currentFinancialYear->start_date).' → '.fmt_date($currentFinancialYear->end_date);
                    @endphp
                    @can('menu', ['financial_years', 'view'])
                        <a href="{{ route('financial-years.index') }}" class="topbar-fy" title="{{ $fyTitle }}">
                            <x-icon name="calendar" class="h-3.5 w-3.5" />
                            <span>{{ $currentFinancialYear->name }}</span>
                        </a>
                    @else
                        <span class="topbar-fy" title="{{ $fyTitle }}">
                            <x-icon name="calendar" class="h-3.5 w-3.5" />
                            <span>{{ $currentFinancialYear->name }}</span>
                        </span>
                    @endcan
                @endif
                @if (isset($shellGodowns) && $shellGodowns->isNotEmpty())
                    <form method="POST" action="{{ route('godown.switch') }}" class="topbar-scope-form">
                        @csrf
                        <label for="work-scope" class="sr-only">{{ __('Work mode') }}</label>
                        <select id="work-scope" name="work_scope" onchange="this.form.submit()"
                                class="topbar-select"
                                title="{{ __('Whole shop uses all places for alerts; bills still pick a place.') }}">
                            <option value="shop" @selected(($workScope ?? 'shop') === 'shop')>{{ __('Whole shop') }}</option>
                            <option value="godown" @selected(($workScope ?? 'shop') === 'godown')>{{ __('One place') }}</option>
                        </select>
                        @if (($workScope ?? 'shop') === 'godown')
                            <label for="godown-switch" class="sr-only">{{ __('Place') }}</label>
                            <select id="godown-switch" name="godown_id" onchange="this.form.submit()" class="topbar-select topbar-select--place">
                                @foreach ($shellGodowns as $g)
                                    <option value="{{ $g->id }}" @selected(optional($currentGodown ?? null)->id === $g->id)>{{ $g->display_name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </form>
                @endif

                <x-lang-switch />

                @php
                    $profileUser = Auth::user();
                    $profileAvatar = $profileUser?->avatarUrl();
                    $profileRole = $profileUser?->role?->display_name;
                    $profileShop = isset($shellCompany) ? $shellCompany->display_name : null;
                @endphp
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="topbar-profile-btn" aria-label="{{ __('Account') }}" :aria-expanded="open.toString()">
                        @if ($profileAvatar)
                            <img src="{{ $profileAvatar }}" alt="" class="topbar-avatar topbar-avatar--photo">
                        @else
                            <span class="topbar-avatar">{{ $profileUser->initials() }}</span>
                        @endif
                        <span class="topbar-profile-meta">
                            <span class="topbar-profile-name">{{ $profileUser->name }}</span>
                            <span class="topbar-profile-role">{{ $profileRole }}</span>
                        </span>
                    </button>
                    <div x-show="open" x-cloak x-transition class="topbar-panel topbar-panel--profile">
                        <div class="topbar-profile-card">
                            <div class="topbar-profile-card__bg" aria-hidden="true"></div>
                            <div class="topbar-profile-card__main">
                                @if ($profileAvatar)
                                    <span class="topbar-profile-avatar topbar-profile-avatar--photo" style="background-image:url('{{ $profileAvatar }}')" aria-hidden="true"></span>
                                @else
                                    <span class="topbar-profile-avatar" aria-hidden="true">{{ $profileUser->initials() }}</span>
                                @endif
                                <div class="topbar-profile-card__meta">
                                    <strong class="topbar-profile-card__name">{{ $profileUser->name }}</strong>
                                    @if ($profileRole)
                                        <span class="topbar-panel__pill">{{ $profileRole }}</span>
                                    @endif
                                    @if ($profileUser->email)
                                        <span class="topbar-profile-card__email">{{ $profileUser->email }}</span>
                                    @endif
                                    @if ($profileUser->phone)
                                        <span class="topbar-profile-card__email">{{ $profileUser->phone }}</span>
                                    @endif
                                    @if ($profileShop)
                                        <span class="topbar-profile-card__store">{{ $profileShop }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="topbar-panel__section">
                            <a href="{{ route('profile.edit') }}" class="topbar-action">
                                <x-icon name="user" class="h-4 w-4 text-leaf" /> {{ __('My Profile') }}
                            </a>
                            <a href="{{ route('profile.edit') }}#password" class="topbar-action">
                                <x-icon name="shield" class="h-4 w-4 text-leaf" /> {{ __('Change password') }}
                            </a>
                            <a href="{{ route('dashboard') }}" class="topbar-action">
                                <x-icon name="home" class="h-4 w-4 text-leaf" /> {{ __('Dashboard') }}
                            </a>
                            @can('menu', ['settings_company', 'view'])
                                <a href="{{ route('settings.company') }}" class="topbar-action">
                                    <x-icon name="building" class="h-4 w-4 text-leaf" /> {{ __('Shop registration') }}
                                </a>
                            @endcan
                            @can('menu', ['settings_mail', 'view'])
                                <a href="{{ route('settings.mail') }}" class="topbar-action">
                                    <x-icon name="mail" class="h-4 w-4 text-leaf" /> {{ __('SMTP / Mail') }}
                                </a>
                            @endcan
                        </div>
                        <div class="topbar-panel__footer">
                            <form method="POST" action="{{ route('logout') }}" class="w-full" data-busy-label="{{ __('Signing out…') }}">
                                @csrf
                                <button type="submit" class="topbar-action topbar-action--danger w-full justify-center" data-busy-label="{{ __('Signing out…') }}">{{ __('Log Out') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="main-pane">
            <div class="page-enter w-full">
                <x-flash />
                {{ $slot }}
            </div>
        </main>
    </div>
</div>

{{-- Global drawer host --}}
<template x-teleport="body">
    <div>
        <div x-show="$store.ui.drawerOpen" x-cloak class="ui-drawer-backdrop" @click="$store.ui.closeDrawer()" x-transition.opacity></div>
        <aside x-show="$store.ui.drawerOpen" x-cloak class="ui-drawer w-full"
               :style="{ width: 'min(100vw, ' + $store.ui.drawerWidth + ')' }"
               x-transition:enter="transition transform ease-out duration-200"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition transform ease-in duration-150"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full">
            <div class="ui-drawer-header">
                <h2 class="font-display text-lg font-semibold text-leaf-dark" x-text="$store.ui.drawerTitle"></h2>
                <button type="button" class="rounded-lg p-2 hover:bg-sage" @click="$store.ui.closeDrawer()" aria-label="{{ __('Close') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="ui-drawer-body" class="flex-1 overflow-y-auto p-4" x-html="$store.ui.drawerHtml"></div>
        </aside>
    </div>
</template>

<template x-teleport="body">
    <div id="ui-toasts" class="pointer-events-none fixed bottom-4 right-4 z-[80] flex w-[min(22rem,calc(100vw-2rem))] flex-col gap-2">
        <template x-for="t in $store.ui.toasts" :key="t.id">
            <div class="pointer-events-auto rounded-xl border px-4 py-3 text-sm shadow-lg"
                 :class="t.type === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-sage-dark bg-white text-ink'"
                 x-text="t.message"></div>
        </template>
    </div>
</template>

<template x-teleport="body">
    <div x-show="$store.ui.confirmOpen" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-leaf-dark/45" @click="$store.ui.resolveConfirm(false)"></div>
        <div class="relative w-full max-w-md rounded-2xl border border-sage-dark bg-white p-5 shadow-xl"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0">
            <h3 class="font-display text-lg font-semibold text-leaf-dark">{{ __('Please confirm') }}</h3>
            <p class="mt-2 text-sm text-ink-soft" x-text="$store.ui.confirmMessage"></p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="btn-secondary" @click="$store.ui.resolveConfirm(false)">{{ __('Cancel') }}</button>
                <button type="button" class="btn-danger" @click="$store.ui.resolveConfirm(true)">{{ __('Yes, continue') }}</button>
            </div>
        </div>
    </div>
</template>

@stack('scripts')
</body>
</html>
