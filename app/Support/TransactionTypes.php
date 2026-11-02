<?php

namespace App\Support;

/** Helpers for config/transaction_types.php */
class TransactionTypes
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $types = config('transaction_types.types', []);
        uasort($types, fn ($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

        return $types;
    }

    /** @return array<string, string> code => label */
    public static function labels(): array
    {
        return collect(self::all())->mapWithKeys(fn ($t, $code) => [$code => __($t['label'])])->all();
    }

    /** @return array<string, string> code => default prefix */
    public static function prefixes(): array
    {
        return collect(self::all())->mapWithKeys(fn ($t, $code) => [$code => $t['prefix']])->all();
    }

    public static function label(string $code): string
    {
        return __(config("transaction_types.types.$code.label") ?? ucfirst(str_replace('_', ' ', $code)));
    }

    public static function groupLabel(string $group): string
    {
        return __(config("transaction_types.groups.$group") ?? ucfirst($group));
    }

    /** @return array<string, array<int, array{code:string, meta:array}>> */
    public static function grouped(): array
    {
        $out = [];
        foreach (self::all() as $code => $meta) {
            $g = $meta['group'] ?? 'other';
            $out[$g][] = ['code' => $code, 'meta' => $meta];
        }

        return $out;
    }
}
