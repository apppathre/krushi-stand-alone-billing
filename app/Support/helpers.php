<?php

use App\Support\NumberToWords;

if (! function_exists('money')) {
    /** Format a number in Indian grouping with 2 decimals (e.g. 1,23,456.00). */
    function money(float|int|string|null $value, int $decimals = 2): string
    {
        $value = (float) $value;
        $negative = $value < 0;
        $value = abs($value);
        $formatted = number_format($value, $decimals, '.', '');
        [$int, $dec] = array_pad(explode('.', $formatted), 2, '');

        if (strlen($int) > 3) {
            $last3 = substr($int, -3);
            $rest = substr($int, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $int = $rest.','.$last3;
        }

        return ($negative ? '-' : '').$int.($decimals > 0 ? '.'.$dec : '');
    }
}

if (! function_exists('qty')) {
    /** Format a quantity: strip trailing zeros from 3 decimals. */
    function qty(float|int|string|null $value): string
    {
        $s = rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');

        return $s === '' ? '0' : $s;
    }
}

if (! function_exists('amount_in_words')) {
    function amount_in_words(float $amount): string
    {
        return NumberToWords::rupees($amount);
    }
}

if (! function_exists('fmt_date')) {
    function fmt_date(mixed $date, string $format = 'd-m-Y'): string
    {
        if (blank($date)) {
            return '';
        }

        return \Illuminate\Support\Carbon::parse($date)->format($format);
    }
}
