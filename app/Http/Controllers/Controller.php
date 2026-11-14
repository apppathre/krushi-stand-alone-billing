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
     * Default = this month (so Recent shows prior bills); respects FY bounds.
     * Presets: today | yesterday | month | fy.
     * Anchor date = today clamped into the active FY (same idea as voucher dates).
     */
    protected function listDateRange(Request $request): array
    {
        $fy = \App\Models\FinancialYear::current();
        $fyStart = $fy->start_date->toDateString();
        $fyEnd = $fy->end_date->toDateString();

        $anchor = now()->toDateString();
        if ($anchor > $fyEnd) {
            $anchor = $fyEnd;
        } elseif ($anchor < $fyStart) {
            $anchor = $fyStart;
        }
        $today = $anchor;
        $monthStart = max(\Illuminate\Support\Carbon::parse($anchor)->startOfMonth()->toDateString(), $fyStart);
        $yesterday = max(\Illuminate\Support\Carbon::parse($anchor)->subDay()->toDateString(), $fyStart);

        $preset = $request->input('preset');
        if (! $request->filled('from') && ! $request->filled('to')) {
            [$from, $to] = match ($preset ?: 'month') {
                'today' => [$today, $today],
                'yesterday' => [$yesterday, $yesterday],
                'fy' => [$fyStart, $today],
                default => [$monthStart, $today], // month (and open with no preset)
            };
        } else {
            $from = $request->input('from') ?: $monthStart;
            $to = $request->input('to') ?: $today;
        }

        $from = max($from, $fyStart);
        $to = min(max($to, $from), $fyEnd);

        return [$from, $to];
    }

    /**
     * Page size for Recent / browse drawers.
     * Allowed: 10 | 20 | 50. Default 10.
     */
    protected function listPerPage(Request $request, int $default = 10): int
    {
        $allowed = [10, 20, 50];
        $n = (int) $request->input('per_page', $default);

        return in_array($n, $allowed, true) ? $n : $default;
    }

    /** Ensure voucher date is inside the active financial year. */
    protected function assertActiveFyDate(string $date, string $field = 'date'): void
    {
        app(\App\Services\FinancialYearService::class)->assertDateInCurrent($date, $field);
    }
}
