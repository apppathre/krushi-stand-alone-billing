<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** Abort unless the signed-in user may work in this godown. */
    protected function authorizeGodown(Request $request, int $godownId): void
    {
        abort_unless(in_array($godownId, $request->user()->accessibleGodownIds(), true), 403, __('You do not have access to this godown.'));
    }

    /** Godown ids to restrict a list to: the selected godown filter or all accessible godowns. */
    protected function godownScope(Request $request): array
    {
        $allowed = $request->user()->accessibleGodownIds();
        $selected = (int) $request->input('godown_id');

        return $selected && in_array($selected, $allowed, true) ? [$selected] : $allowed;
    }

    /** Default date range: from start of active financial year to today (clamped). */
    protected function dateRange(Request $request): array
    {
        $fy = \App\Models\FinancialYear::current();
        $from = $request->input('from') ?: $fy->start_date->toDateString();
        $to = $request->input('to') ?: min(now()->toDateString(), $fy->end_date->toDateString());

        return [$from, $to];
    }

    /**
     * List/filter date range for sale & purchase desks.
     * Default = today; respects FY bounds. Presets: today | yesterday | month.
     */
    protected function listDateRange(Request $request): array
    {
        $fy = \App\Models\FinancialYear::current();
        $fyStart = $fy->start_date->toDateString();
        $fyEnd = $fy->end_date->toDateString();
        $today = min(now()->toDateString(), $fyEnd);

        $preset = $request->input('preset');
        if (! $request->filled('from') && ! $request->filled('to') && $preset) {
            [$from, $to] = match ($preset) {
                'yesterday' => [
                    now()->subDay()->toDateString(),
                    now()->subDay()->toDateString(),
                ],
                'month' => [
                    now()->startOfMonth()->toDateString(),
                    $today,
                ],
                default => [$today, $today],
            };
        } else {
            $from = $request->input('from') ?: $today;
            $to = $request->input('to') ?: $today;
        }

        $from = max($from, $fyStart);
        $to = min(max($to, $from), $fyEnd);

        return [$from, $to];
    }

    /** Ensure voucher date is inside the active financial year. */
    protected function assertActiveFyDate(string $date, string $field = 'date'): void
    {
        app(\App\Services\FinancialYearService::class)->assertDateInCurrent($date, $field);
    }
}
