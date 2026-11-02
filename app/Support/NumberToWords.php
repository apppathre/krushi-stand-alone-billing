<?php

namespace App\Support;

class NumberToWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    public static function rupees(float $amount): string
    {
        $amount = round(abs($amount), 2);
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        $words = 'Rupees '.($rupees === 0 ? 'Zero' : self::convert($rupees));
        if ($paise > 0) {
            $words .= ' and '.self::convert($paise).' Paise';
        }

        return $words.' Only';
    }

    public static function convert(int $n): string
    {
        if ($n === 0) {
            return '';
        }

        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']] as [$div, $label]) {
            if ($n >= $div) {
                $parts[] = self::convert(intdiv($n, $div)).' '.$label;
                $n %= $div;
            }
        }

        if ($n > 0) {
            $parts[] = $n < 20 ? self::ONES[$n] : self::TENS[intdiv($n, 10)].($n % 10 ? ' '.self::ONES[$n % 10] : '');
        }

        return implode(' ', $parts);
    }
}
