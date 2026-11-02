<?php

namespace App\Http\Controllers;

use App\Services\AppMaintenanceService;
use App\Services\TableIndexService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Owner admin: caches, migrate, table indexes, master reseed — for live servers without SSH. */
class MaintenanceController extends Controller
{
    public function __construct(
        private AppMaintenanceService $maintenance,
        private TableIndexService $indexes,
    ) {}

    public function index(): View
    {
        $indexRows = $this->indexes->status();

        return view('maintenance.index', [
            'connected' => $this->maintenance->databaseConnected(),
            'pendingMigrations' => $this->maintenance->hasPendingMigrations(),
            'indexRows' => $indexRows,
            'indexesReady' => collect($indexRows)->where('present', true)->count(),
            'indexesMissing' => collect($indexRows)->where('present', false)->where('skipped', false)->count(),
            'pdfFonts' => \App\Support\PrintFonts::statusForUi(),
        ]);
    }

    public function deploy(Request $request): RedirectResponse
    {
        $result = $this->maintenance->installOrDeploy(
            seedMasters: $request->boolean('sync_masters'),
            fullSeed: false,
        );

        return back()
            ->with($result['ok'] ? 'status' : 'error', $result['message'])
            ->with('maintenance_steps', $result['steps']);
    }

    public function clearCaches(): RedirectResponse
    {
        $result = $this->maintenance->clearAppCaches();

        return back()->with('status', $result['message']);
    }

    public function applyIndexes(): RedirectResponse
    {
        try {
            $result = $this->indexes->apply();
            $msg = $result['created'] === 0
                ? __('Table indexes were already in place. Nothing to add.')
                : __(':n index(es) added; :a table(s) analyzed.', [
                    'n' => $result['created'],
                    'a' => $result['analyzed'],
                ]);

            return back()->with('status', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', __('Indexes failed: :err', ['err' => $e->getMessage()]));
        }
    }

    public function syncMasters(): RedirectResponse
    {
        $result = $this->maintenance->installOrDeploy(seedMasters: true, fullSeed: false);
        // Skip migrate noise — only keep seed steps in flash context
        return back()
            ->with($result['ok'] ? 'status' : 'error', $result['message'])
            ->with('maintenance_steps', $result['steps']);
    }

    /** Re-copy bundled Noto Devanagari TTFs into storage/fonts for mPDF. */
    public function installPdfFonts(Request $request): RedirectResponse
    {
        $result = $this->maintenance->installPdfFonts(force: $request->boolean('force', true));

        return back()
            ->with($result['ok'] ? 'status' : 'error', $result['message'])
            ->with('maintenance_steps', [[
                'step' => 'pdf_fonts',
                'ok' => $result['ok'],
                'message' => $result['message'],
            ]]);
    }
}
