<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Install') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-cream text-ink antialiased">
    <main class="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-4 py-10">
        <div class="card card-body space-y-4">
            <div>
                <h1 class="font-display text-2xl font-semibold text-leaf-dark">{{ __('Install Krushi billing') }}</h1>
                <p class="mt-1 text-sm text-ink-soft">{{ __('One click: migrate database, add table indexes, and seed masters. No SSH needed.') }}</p>
            </div>

            <ul class="space-y-1 text-sm">
                <li>{{ __('Database') }}:
                    @if ($connected)
                        <strong class="text-leaf">{{ __('Connected') }}</strong>
                    @else
                        <strong class="text-accent">{{ __('Not connected — fix .env DB_* first') }}</strong>
                    @endif
                </li>
                <li>{{ __('Already installed') }}: <strong>{{ $installed ? __('Yes') : __('No') }}</strong></li>
                <li>{{ __('Pending migrations') }}: <strong>{{ $pending ? __('Yes') : __('No') }}</strong></li>
                <li>{{ __('Owner user') }}:
                    @if ($needsOwner ?? true)
                        <strong class="text-accent">{{ __('Missing — seed required') }}</strong>
                    @else
                        <strong class="text-leaf">{{ __('Ready') }}</strong>
                    @endif
                </li>
                <li>{{ __('Marathi PDF fonts (mPDF)') }}:
                    @if (($pdfFonts['available'] ?? false))
                        <strong class="text-leaf">{{ __('Ready') }}</strong>
                    @elseif (! ($pdfFonts['source_ok'] ?? false))
                        <strong class="text-accent">{{ __('Missing in code — push resources/fonts/devanagari/*.ttf') }}</strong>
                    @else
                        <strong class="text-accent">{{ __('Will install on this run') }}</strong>
                    @endif
                </li>
            </ul>

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            @if (! empty($steps))
                <div class="rounded-lg border border-sage bg-white px-3 py-2 text-sm">
                    @foreach ($steps as $step)
                        <p class="{{ ($step['ok'] ?? false) ? 'text-leaf' : 'text-red-700' }}">
                            <strong>{{ $step['step'] ?? '' }}</strong> — {{ $step['message'] ?? '' }}
                        </p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('setup.install.run') }}" class="space-y-3" data-busy-label="{{ __('Installing…') }}">
                @csrf
                @if (! $installed || ($needsOwner ?? false))
                    <label class="inline-flex items-center gap-2 text-sm font-semibold">
                        <input type="checkbox" name="full_seed" value="1" class="checkbox" checked>
                        {{ __('Full seed (company, menus, ledgers, sample catalog, owner user)') }}
                    </label>
                    <p class="text-xs text-ink-soft">
                        {{ __('Owner login after install') }}:
                        <span class="font-semibold text-ink">{{ \Database\Seeders\UserSeeder::OWNER_EMAIL }}</span>
                        / <span class="font-semibold text-ink">{{ \Database\Seeders\UserSeeder::OWNER_PASSWORD }}</span>
                    </p>
                @endif
                <button type="submit" class="btn-primary w-full" @disabled(! $connected)>
                    @if ($needsOwner ?? false)
                        {{ __('Create owner & finish setup') }}
                    @elseif ($installed)
                        {{ __('Apply pending updates') }}
                    @else
                        {{ __('Install now') }}
                    @endif
                </button>
            </form>

            @if ($needsOwner ?? false)
                <details class="rounded-lg border border-sage bg-white px-3 py-2 text-xs text-ink-soft">
                    <summary class="cursor-pointer font-semibold text-ink">{{ __('phpMyAdmin fallback (if Install button fails)') }}</summary>
                    <p class="mt-2">{{ __('Your earlier INSERT put 0 rows because roles had no owner slug. Run both statements:') }}</p>
                    <pre class="mt-2 overflow-x-auto whitespace-pre-wrap break-all rounded bg-cream p-2 text-[11px] text-ink">INSERT INTO roles (name, name_mr, slug, description, is_system, created_at, updated_at)
VALUES ('Owner', 'मालक', 'owner', 'Full access to everything', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), is_system = 1;

INSERT INTO users (name, email, password, role_id, is_active, email_verified_at, locale, created_at, updated_at)
SELECT 'Shop Owner', 'appathre@gmail.com',
  '$2y$12$Frs4qrQtZ1hgLr4SLlHJ5uadkuXZ85sGJP1vuKt0Bv8j3TbIMjKZ6',
  id, 1, NOW(), 'en', NOW(), NOW()
FROM roles WHERE slug = 'owner' LIMIT 1
ON DUPLICATE KEY UPDATE
  password = VALUES(password), is_active = 1, role_id = VALUES(role_id), name = VALUES(name);</pre>
                    <p class="mt-2">{{ __('Then login') }}: appathre@gmail.com / Prasad@123</p>
                </details>
            @endif

            <p class="text-center text-xs text-ink-soft">
                <a href="{{ route('login') }}" class="text-leaf hover:underline">{{ __('Back to login') }}</a>
            </p>
        </div>
    </main>
</body>
</html>
