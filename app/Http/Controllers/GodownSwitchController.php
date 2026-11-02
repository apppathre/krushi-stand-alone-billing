<?php

namespace App\Http\Controllers;

use App\Support\WorkContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GodownSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'godown_id' => ['nullable', 'integer'],
            'work_scope' => ['nullable', 'in:shop,godown'],
        ]);

        if (! empty($data['work_scope'])) {
            $request->session()->put('work_scope', $data['work_scope']);
        }

        if (! empty($data['godown_id'])) {
            abort_unless(in_array((int) $data['godown_id'], $request->user()->accessibleGodownIds(), true), 403);
            $request->session()->put('godown_id', (int) $data['godown_id']);
            // Picking a place implies focused godown mode.
            if (empty($data['work_scope'])) {
                $request->session()->put('work_scope', WorkContext::SCOPE_GODOWN);
            }
        }

        $message = ! empty($data['work_scope']) && $data['work_scope'] === WorkContext::SCOPE_SHOP
            ? __('Working across the whole shop. Pick a place on each bill for stock.')
            : __('Place / godown updated.');

        return back()->with('status', $message);
    }
}
