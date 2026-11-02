<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPriceLog;

class ProductPriceLogger
{
    /** @param  array<string, float|int|string|null>  $before */
    /** @param  array<string, float|int|string|null>  $after */
    public function logChanges(Product $product, array $before, array $after, ?int $userId, ?string $note = null): void
    {
        foreach (['sale_rate', 'mrp', 'purchase_rate'] as $field) {
            if (! array_key_exists($field, $after)) {
                continue;
            }
            $old = round((float) ($before[$field] ?? 0), 2);
            $new = round((float) ($after[$field] ?? 0), 2);
            if (abs($old - $new) < 0.005) {
                continue;
            }
            ProductPriceLog::create([
                'product_id' => $product->id,
                'user_id' => $userId,
                'field' => $field,
                'old_value' => $old,
                'new_value' => $new,
                'note' => $note,
            ]);
        }
    }
}
