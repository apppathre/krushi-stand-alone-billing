<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Server maintenance') }}</h1>
            <p class="text-xs text-ink-soft">{{ __('Live hosting tools — migrate, indexes, caches, master sync. No SSH required.') }}</p>
        </div>
    </x-slot>

    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    @if (session('maintenance_steps'))
        <div class="card card-body mb-4">
            <h2 class="mb-2 font-semibold text-leaf-dark">{{ __('Last run steps') }}</h2>
            <ul class="space-y-1 text-sm">
                @foreach (session('maintenance_steps') as $step)
                    <li class="{{ ($step['ok'] ?? false) ? 'text-leaf' : 'text-red-700' }}">
                        <strong>{{ $step['step'] ?? '' }}</strong> — {{ $step['message'] ?? '' }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-body mb-4 space-y-3">
        <h2 class="font-display text-lg font-semibold text-leaf-dark">{{ __('Post-deploy') }}</h2>
        <p class="text-sm text-ink-soft">
            {{ __('Database') }}:
            @if ($connected)
                <span class="font-semibold text-leaf">{{ __('Connected') }}</span>
            @else
                <span class="font-semibold text-accent">{{ __('Not connected') }}</span>
            @endif
            · {{ __('Pending migrations') }}:
            <strong>{{ $pendingMigrations ? __('Yes') : __('No') }}</strong>
        </p>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('maintenance.deploy') }}"
                  onsubmit="return confirm(@js(__('Clear caches, run migrations, and add missing table indexes?')))">
                @csrf
                <input type="hidden" name="sync_masters" value="0">
                <button class="btn-primary" data-busy-label="{{ __('Working…') }}">{{ __('Run migrate + indexes') }}</button>
            </form>
            <form method="POST" action="{{ route('maintenance.deploy') }}"
                  onsubmit="return confirm(@js(__('Also re-sync menus, GST/HSN, and areas?')))">
                @csrf
                <input type="hidden" name="sync_masters" value="1">
                <button class="btn-secondary" data-busy-label="{{ __('Working…') }}">{{ __('Migrate + indexes + sync masters') }}</button>
            </form>
            <form method="POST" action="{{ route('maintenance.clear-caches') }}"
                  onsubmit="return confirm(@js(__('Clear route / config / view caches?')))">
                @csrf
                <button class="btn-secondary" data-busy-label="{{ __('Clearing…') }}">{{ __('Clear caches') }}</button>
            </form>
            <form method="POST" action="{{ route('maintenance.indexes') }}"
                  onsubmit="return confirm(@js(__('Add any missing table indexes? Existing ones stay as they are.')))">
                @csrf
                <button class="btn-secondary" data-busy-label="{{ __('Working…') }}">{{ __('Run table indexes') }}</button>
            </form>
            <form method="POST" action="{{ route('maintenance.sync-masters') }}"
                  onsubmit="return confirm(@js(__('Re-seed menus/roles, GST/HSN catalog, and areas?')))">
                @csrf
                <button class="btn-secondary" data-busy-label="{{ __('Syncing…') }}">{{ __('Sync masters only') }}</button>
            </form>
        </div>
        <p class="text-xs text-ink-soft">{{ __('First-time empty server: open /install instead (full seed). This page is for owners after login.') }}</p>
    </div>

    <div class="card card-body">
        <h2 class="mb-1 font-display text-lg font-semibold text-leaf-dark">{{ __('Table indexes') }}</h2>
        <p class="mb-3 text-sm text-ink-soft">
            {{ $indexesReady }} {{ __('in place') }}@if ($indexesMissing), {{ $indexesMissing }} {{ __('still to add') }}@endif.
            {{ __('Run indexes only adds what is missing.') }}
        </p>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Table') }}</th>
                        <th>{{ __('Index') }}</th>
                        <th>{{ __('Why') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($indexRows as $row)
                        <tr>
                            <td><code class="text-xs">{{ $row['table'] }}</code></td>
                            <td>
                                <strong class="text-xs">{{ $row['name'] }}</strong>
                                <span class="block text-xs text-ink-soft">{{ implode(' + ', $row['columns']) }}</span>
                            </td>
                            <td class="text-xs">{{ $row['why'] }}</td>
                            <td>
                                @if ($row['skipped'])
                                    <span class="text-xs text-ink-soft">{{ __('No table') }}</span>
                                @elseif ($row['present'])
                                    <span class="text-xs font-semibold text-leaf">{{ __('Ready') }}</span>
                                @else
                                    <span class="text-xs font-semibold text-accent">{{ __('Missing') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
