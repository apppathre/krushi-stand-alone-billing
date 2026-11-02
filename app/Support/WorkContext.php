<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Shop work mode: "shop" = whole shop view (all accessible godowns for reports/alerts),
 * "godown" = focus one place/godown. Stock vouchers always post to a specific place.
 */
class WorkContext
{
    public const SCOPE_SHOP = 'shop';

    public const SCOPE_GODOWN = 'godown';

    public static function scope(Request $request): string
    {
        $scope = (string) $request->session()->get('work_scope', self::SCOPE_SHOP);

        return in_array($scope, [self::SCOPE_SHOP, self::SCOPE_GODOWN], true)
            ? $scope
            : self::SCOPE_SHOP;
    }

    public static function isShop(Request $request): bool
    {
        return self::scope($request) === self::SCOPE_SHOP;
    }

    /** Godown ids used for stock totals / alerts / dashboard. */
    public static function stockGodownIds(Request $request): array
    {
        $allowed = $request->user()->accessibleGodownIds();
        if (self::isShop($request)) {
            return $allowed;
        }

        $id = (int) $request->session()->get('godown_id');

        return $id && in_array($id, $allowed, true) ? [$id] : $allowed;
    }

    /** Default place for issuing/receiving stock on a voucher. */
    public static function placeId(Request $request): int
    {
        $allowed = $request->user()->accessibleGodownIds();
        $requested = (int) $request->input('godown_id', $request->session()->get('godown_id'));
        if ($requested && in_array($requested, $allowed, true)) {
            return $requested;
        }

        return (int) ($allowed[0] ?? 0);
    }
}
