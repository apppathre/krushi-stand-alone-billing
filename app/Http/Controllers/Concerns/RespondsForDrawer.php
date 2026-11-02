<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shared helpers for controllers whose create/edit forms open in the AJAX side drawer.
 * Mirrors the behaviour of MasterController.
 */
trait RespondsForDrawer
{
    protected function wantsDrawer(Request $request): bool
    {
        return $request->header('X-UI-Drawer') === '1'
            || $request->boolean('drawer')
            || $request->ajax();
    }

    protected function wantsJsonResponse(Request $request): bool
    {
        return $request->expectsJson() || $this->wantsDrawer($request);
    }

    protected function savedResponse(Request $request, string $message, string $route): JsonResponse|RedirectResponse
    {
        if ($this->wantsJsonResponse($request)) {
            return response()->json(['ok' => true, 'message' => $message, 'reload' => true]);
        }

        return redirect()->route($route)->with('status', $message);
    }

    protected function failedResponse(Request $request, string $message, string $errorKey = 'delete'): JsonResponse|RedirectResponse
    {
        if ($this->wantsJsonResponse($request)) {
            return response()->json(['ok' => false, 'message' => $message], 422);
        }

        return back()->withErrors([$errorKey => $message]);
    }
}
