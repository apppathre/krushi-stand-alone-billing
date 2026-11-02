<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMap extends Model
{
    protected $guarded = [];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function salesLedger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'sales_ledger_id');
    }

    public function purchaseLedger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'purchase_ledger_id');
    }

    /**
     * Ledger override for a product: product-level map wins, then category-level.
     *
     * @param  'sales_ledger_id'|'purchase_ledger_id'  $column
     */
    public static function resolve(Product $product, string $column): ?int
    {
        $byProduct = static::where('scope', 'product')->where('product_id', $product->id)->value($column);
        if ($byProduct) {
            return (int) $byProduct;
        }
        $byCategory = static::where('scope', 'category')->where('category_id', $product->category_id)->value($column);

        return $byCategory ? (int) $byCategory : null;
    }
}
