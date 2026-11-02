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
        $needsOwner = $this->maintenance->needsOwnerSeed();

        // Empty users after wipe / failed seed — keep /install open so owner can be created.
        if ($installed && ! $pending && ! $needsOwner) {
            return redirect()->route('login')->with(
                'status',
                __('Database is already installed. Sign in — use Administration → Server maintenance for updates.')
            );
        }

        return view('setup.install', [
            'connected' => $connected,
            'installed' => $installed,
            'pending' => $pending,
            'needsOwner' => $needsOwner,
            'steps' => session('install_steps', []),
            'pdfFonts' => \App\Support\PrintFonts::statusForUi(),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $installed = $this->maintenance->isInstalled();
        $needsOwner = $this->maintenance->needsOwnerSeed();
        // Fresh DB, or schema with no login: always full-seed when asked (default on).
        $fullSeed = (! $installed || $needsOwner) && $request->boolean('full_seed', true);

        $result = $this->maintenance->installOrDeploy(
            seedMasters: $installed && ! $fullSeed, // updates only when not doing full seed
            fullSeed: $fullSeed,
        );

        if (! $result['ok']) {
            return back()
                ->withErrors(['install' => $result['message']])
                ->with('install_steps', $result['steps']);
        }

        $message = $this->maintenance->isInstalled()
            ? __('Setup complete. Sign in as :email / password :password', [
                'email' => \Database\Seeders\UserSeeder::OWNER_EMAIL,
                'password' => \Database\Seeders\UserSeeder::OWNER_PASSWORD,
            ])
            : __('Setup ran, but install check failed. Verify .env and try again.');

        return redirect()->route('login')->with('status', $message);
    }
}
