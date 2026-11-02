<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Services\AppMaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * First-time / live hosting install without SSH.
 * Locked once the app is already installed (unless pending migrations).
 */
class InstallController extends Controller
{
    public function __construct(private AppMaintenanceService $maintenance) {}

    public function show(): View|RedirectResponse
    {
        $connected = $this->maintenance->databaseConnected();
        $installed = $this->maintenance->isInstalled();
        $pending = $this->maintenance->hasPendingMigrations();

        if ($installed && ! $pending) {
            return redirect()->route('login')->with(
                'status',
                __('Database is already installed. Sign in — use Administration → Server maintenance for updates.')
            );
        }

        return view('setup.install', [
            'connected' => $connected,
            'installed' => $installed,
            'pending' => $pending,
            'steps' => session('install_steps', []),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $installed = $this->maintenance->isInstalled();
        $fullSeed = ! $installed && $request->boolean('full_seed', true);

        $result = $this->maintenance->installOrDeploy(
            seedMasters: $installed, // on update: refresh menus/tax/areas
            fullSeed: $fullSeed,
        );

        if (! $result['ok']) {
            return back()
                ->withErrors(['install' => $result['message']])
                ->with('install_steps', $result['steps']);
        }

        $message = $this->maintenance->isInstalled()
            ? ($fullSeed
                ? __('Setup complete. Sign in as :email', ['email' => \Database\Seeders\UserSeeder::OWNER_EMAIL])
                : __('Database updates applied. Sign in to continue.'))
            : __('Setup ran, but install check failed. Verify .env and try again.');

        return redirect()->route('login')->with('status', $message);
    }
}
