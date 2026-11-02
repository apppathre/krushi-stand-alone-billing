<?php

namespace App\Services;

class GstCalculator
{
    /** Inter-state supply (IGST) when the place of supply differs from the company state. */
    public function isInterState(?string $companyState, ?string $supplyState): bool
    {
        return filled($companyState) && filled($supplyState) && $companyState !== $supplyState;
    }

    /**
     * Calculate one line (tax-exclusive pricing).
     *
     * @return array{gross: float, discount_amount: float, taxable_amount: float, gst_rate: float, cgst_amount: float, sgst_amount: float, igst_amount: float, line_total: float}
     */
    public function line(float $qty, float $rate, float $discountPct, float $gstRate, bool $interState): array
    {
        $gross = round($qty * $rate, 2);
        $discount = round($gross * max(0, min(100, $discountPct)) / 100, 2);
        $taxable = round($gross - $discount, 2);
        $tax = round($taxable * $gstRate / 100, 2);

        $cgst = $sgst = $igst = 0.0;
        if ($interState) {
            $igst = $tax;
        } else {
            $cgst = round($tax / 2, 2);
            $sgst = round($tax - $cgst, 2);
        }

        return [
            'gross' => $gross,
            'discount_amount' => $discount,
            'taxable_amount' => $taxable,
            'gst_rate' => $gstRate,
            'cgst_amount' => $cgst,
            'sgst_amount' => $sgst,
            'igst_amount' => $igst,
            'line_total' => round($taxable + $tax, 2),
        ];
    }

    /**
     * Calculate a list of items. Each item needs qty, rate, discount_pct, gst_rate.
     * Item keys are preserved and merged with the computed amounts.
     *
     * @return array{lines: array<int, array>, totals: array<string, float>}
     */
    public function calculate(array $items, bool $interState): array
    {
        $lines = [];
        foreach ($items as $i => $item) {
            $lines[$i] = $item + $this->line(
                (float) $item['qty'],
                (float) $item['rate'],
                (float) ($item['discount_pct'] ?? 0),
                (float) ($item['gst_rate'] ?? 0),
                $interState,
            );
        }

        return ['lines' => $lines, 'totals' => $this->totals($lines)];
    }

    /** @param  array<int, array>  $lines */
    public function totals(array $lines): array
    {
        $sum = fn (string $key) => round(array_sum(array_column($lines, $key)), 2);

        $subtotal = $sum('gross');
        $discount = $sum('discount_amount');
        $taxable = $sum('taxable_amount');
        $cgst = $sum('cgst_amount');
        $sgst = $sum('sgst_amount');
        $igst = $sum('igst_amount');
        $beforeRound = round($taxable + $cgst + $sgst + $igst, 2);
        $grand = (float) round($beforeRound, 0);

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'cash_discount' => 0.0,
            'freight_amount' => 0.0,
            'hammali_amount' => 0.0,
            'taxable_total' => $taxable,
            'cgst_total' => $cgst,
            'sgst_total' => $sgst,
            'igst_total' => $igst,
            'round_off' => round($grand - $beforeRound, 2),
            'grand_total' => $grand,
        ];
    }

    /**
     * Apply bill-level freight / hammali / cash discount and re-round grand total.
     *
     * @param  array<string, float>  $totals
     * @return array<string, float>
     */
    public function applyBillCharges(array $totals, float $freight = 0, float $hammali = 0, float $cashDiscount = 0): array
    {
        $freight = max(0, round($freight, 2));
        $hammali = max(0, round($hammali, 2));
        $cashDiscount = max(0, round($cashDiscount, 2));

        $goods = round(
            (float) $totals['taxable_total']
            + (float) $totals['cgst_total']
            + (float) $totals['sgst_total']
            + (float) $totals['igst_total'],
            2
        );
        $maxCash = max(0, round($goods + $freight + $hammali, 2));
        if ($cashDiscount > $maxCash) {
            $cashDiscount = $maxCash;
        }

        $beforeRound = round($goods + $freight + $hammali - $cashDiscount, 2);
        $grand = (float) round($beforeRound, 0);

        return array_merge($totals, [
            'freight_amount' => $freight,
            'hammali_amount' => $hammali,
            'cash_discount' => $cashDiscount,
            'round_off' => round($grand - $beforeRound, 2),
            'grand_total' => $grand,
        ]);
    }
}
