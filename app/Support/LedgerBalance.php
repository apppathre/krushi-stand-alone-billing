<?php

namespace App\Support;

use App\Models\Ledger;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * Account balances from the single money log (`ledger_entries`) plus ledger opening.
 * Positive = Dr, negative = Cr.
 */
class LedgerBalance
{
    /**
     * @return array{amount: float, side: string, label: string, signed: float}
     */
    public static function asOf(int $ledgerId, ?string $on = null): array
    {
        $ledger = Ledger::find($ledgerId);
        if (! $ledger) {
            return ['amount' => 0.0, 'side' => 'dr', 'label' => '0.00 Dr', 'signed' => 0.0];
        }

        $signed = $ledger->signedOpening();

        $q = LedgerEntry::query()->where('ledger_id', $ledgerId);
        if ($on) {
            $q->whereDate('entry_date', '<=', $on);
        }
        $signed += (float) $q->value(DB::raw('COALESCE(SUM(debit - credit), 0)'));
        $signed = round($signed, 2);

        $abs = abs($signed);
        $side = $signed < -0.009 ? 'cr' : 'dr';
        if ($abs < 0.005) {
            $side = 'dr';
            $abs = 0.0;
        }

        return [
            'amount' => $abs,
            'side' => $side,
            'label' => number_format($abs, 2).' '.strtoupper($side),
            'signed' => $signed,
        ];
    }

    /** Movement total only (no opening) up to a date. */
    public static function movementTotal(int $ledgerId, ?string $before = null, ?string $onOrBefore = null): float
    {
        $q = LedgerEntry::query()->where('ledger_id', $ledgerId);
        if ($before) {
            $q->whereDate('entry_date', '<', $before);
        }
        if ($onOrBefore) {
            $q->whereDate('entry_date', '<=', $onOrBefore);
        }

        return round((float) $q->value(DB::raw('COALESCE(SUM(debit - credit), 0)')), 2);
    }
}
