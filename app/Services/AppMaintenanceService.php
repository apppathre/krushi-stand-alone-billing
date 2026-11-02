<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Live-server friendly: migrate, indexes, cache clear, menu/master reseed — no SSH required.
 */
class AppMaintenanceService
{
    public function __construct(private TableIndexService $indexes) {}

    public function databaseConnected(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function isInstalled(): bool
    {
        if (! $this->databaseConnected()) {
            return false;
        }

        try {
            return Schema::hasTable('migrations')
                && Schema::hasTable('users')
                && Schema::hasTable('roles')
                && Schema::hasTable('company_profile');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Schema exists but nobody can sign in (seed timed out / tables wiped). */
    public function needsOwnerSeed(): bool
    {
        if (! $this->databaseConnected()) {
            return false;
        }

        try {
            if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
                return true;
            }

            return ! DB::table('users')->where('is_active', true)->exists();
        } catch (\Throwable) {
            return true;
        }
    }

    public function hasPendingMigrations(): bool
    {
        if (! $this->databaseConnected()) {
            return false;
        }

        try {
            $migrator = app('migrator');
            if (! $migrator->repositoryExists()) {
                return true;
            }
            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());

            return $pending !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{ok: bool, message: string, removed: list<string>}
     */
    public function clearAppCaches(): array
    {
        $removed = [];

        try {
            Artisan::call('optimize:clear');
            $out = trim(Artisan::output());
            if ($out !== '') {
                $removed[] = 'artisan:optimize:clear';
            }
        } catch (\Throwable) {
        }

        foreach ([
            base_path('bootstrap/cache/routes.php'),
            base_path('bootstrap/cache/routes-v7.php'),
            base_path('bootstrap/cache/config.php'),
            base_path('bootstrap/cache/services.php'),
            base_path('bootstrap/cache/packages.php'),
            base_path('bootstrap/cache/events.php'),
        ] as $file) {
            if (is_file($file) && @unlink($file)) {
                $removed[] = basename($file);
            }
        }

        foreach (glob(base_path('bootstrap/cache/routes*.php')) ?: [] as $file) {
            if (is_file($file) && @unlink($file)) {
                $name = basename($file);
                if (! in_array($name, $removed, true)) {
                    $removed[] = $name;
                }
            }
        }

        $viewCount = 0;
        $views = storage_path('framework/views');
        if (is_dir($views)) {
            foreach (glob($views.DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
                if (is_file($file) && @unlink($file)) {
                    $viewCount++;
                }
            }
        }
        if ($viewCount > 0) {
            $removed[] = $viewCount.' compiled view(s)';
        }

        $message = $removed === []
            ? __('App caches were already clear.')
            : __('Cleared: :list.', ['list' => implode(', ', $removed)]);

        return ['ok' => true, 'message' => $message, 'removed' => $removed];
    }

    /**
     * Full live install / post-deploy: cache → migrate → indexes → optional seed.
     *
     * @return array{ok: bool, message: string, steps: list<array{step:string,ok:bool,message:string}>}
     */
    public function installOrDeploy(bool $seedMasters = false, bool $fullSeed = false): array
    {
        $steps = [];

        $cache = $this->clearAppCaches();
        $steps[] = ['step' => 'cache', 'ok' => $cache['ok'], 'message' => $cache['message']];

        if (! $this->databaseConnected()) {
            $msg = __('Cannot connect to database. Check .env DB_* settings.');
            $steps[] = ['step' => 'database', 'ok' => false, 'message' => $msg];

            return ['ok' => false, 'message' => $msg, 'steps' => $steps];
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $steps[] = ['step' => 'migrate', 'ok' => true, 'message' => trim(Artisan::output()) ?: __('Migrations applied.')];
        } catch (\Throwable $e) {
            $msg = __('Migration failed: :err', ['err' => $e->getMessage()]);
            $steps[] = ['step' => 'migrate', 'ok' => false, 'message' => $msg];

            return ['ok' => false, 'message' => $msg, 'steps' => $steps];
        }

        try {
            $idx = $this->indexes->apply();
            $idxMsg = $idx['created'] === 0
                ? __('Table indexes already in place.')
                : __(':n table index(es) added.', ['n' => $idx['created']]);
            $steps[] = ['step' => 'indexes', 'ok' => true, 'message' => $idxMsg];
        } catch (\Throwable $e) {
            $msg = __('Indexes failed: :err', ['err' => $e->getMessage()]);
            $steps[] = ['step' => 'indexes', 'ok' => false, 'message' => $msg];

            return ['ok' => false, 'message' => $msg, 'steps' => $steps];
        }

        if ($fullSeed) {
            // Seed in order; owner is early in DatabaseSeeder. If a late seeder fails, still force owner.
            try {
                Artisan::call('db:seed', ['--force' => true]);
                $steps[] = ['step' => 'seed', 'ok' => true, 'message' => __('Full database seed completed.')];
            } catch (\Throwable $e) {
                $steps[] = [
                    'step' => 'seed',
                    'ok' => false,
                    'message' => __('Seed failed: :err — trying owner login next.', ['err' => $e->getMessage()]),
                ];
            }
        } elseif ($seedMasters) {
            foreach ([
                ['AccessSeeder', __('Menus & roles synced.')],
                ['UserSeeder', __('Default owner login upserted.')],
                ['LedgerSeeder', __('Default ledgers (rent, salary, interest) synced.')],
                ['TaxCatalogSeeder', __('GST slabs & HSN catalog upserted.')],
                ['AreaSeeder', __('Areas upserted.')],
                ['CatalogSeeder', __('Manufacturers, packings & catalog items upserted.')],
            ] as [$class, $okMsg]) {
                try {
                    Artisan::call('db:seed', [
                        '--class' => 'Database\\Seeders\\'.$class,
                        '--force' => true,
                    ]);
                    $steps[] = ['step' => strtolower($class), 'ok' => true, 'message' => $okMsg];
                } catch (\Throwable $e) {
                    $msg = __(':class failed: :err', ['class' => $class, 'err' => $e->getMessage()]);
                    $steps[] = ['step' => strtolower($class), 'ok' => false, 'message' => $msg];
                    // Keep going for non-owner seeders; owner is required.
                    if ($class === 'AccessSeeder' || $class === 'UserSeeder') {
                        return ['ok' => false, 'message' => $msg, 'steps' => $steps];
                    }
                }
            }
        }

        // Always guarantee an active owner after wipe / timeout / partial seed.
        if ($this->needsOwnerSeed() || $fullSeed || $seedMasters) {
            try {
                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\AccessSeeder',
                    '--force' => true,
                ]);
                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\UserSeeder',
                    '--force' => true,
                ]);
                $steps[] = [
                    'step' => 'owner',
                    'ok' => true,
                    'message' => __('Owner login ready: :email', ['email' => \Database\Seeders\UserSeeder::OWNER_EMAIL]),
                ];
            } catch (\Throwable $e) {
                $msg = __('Owner seed failed: :err', ['err' => $e->getMessage()]);
                $steps[] = ['step' => 'owner', 'ok' => false, 'message' => $msg];

                return ['ok' => false, 'message' => $msg, 'steps' => $steps];
            }
        }

        return [
            'ok' => true,
            'message' => __('Install / deploy steps finished.'),
            'steps' => $steps,
        ];
    }
}
