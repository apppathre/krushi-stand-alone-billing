<?php

namespace App\Support;

/**
 * On-screen help for inventory screens: what it is, when to use, when not to.
 */
class InventoryHelp
{
    /**
     * @return array{title:string, summary:string, do:list<string>, dont:list<string>, tip?:string}
     */
    public static function get(string $key): array
    {
        $all = self::all();

        return $all[$key] ?? [
            'title' => __('Help'),
            'summary' => __('Stock quantity is the sum of movements. Use the voucher that matches what happened.'),
            'do' => [],
            'dont' => [],
        ];
    }

    /** @return array<string, array{title:string, summary:string, do:list<string>, dont:list<string>, tip?:string}> */
    public static function all(): array
    {
        return [
            'stock' => [
                'title' => __('Stock on hand'),
                'summary' => __('Read-only view of quantity by product and batch. Qty comes from the sum of stock movements — there is no separate “qty” column to edit.'),
                'do' => [
                    __('Check availability before billing.'),
                    __('Open low-stock alerts to reorder.'),
                    __('Open a product’s movements to see how qty changed.'),
                ],
                'dont' => [
                    __('Do not try to “fix” qty here — use Purchase, Opening, Physical Stock, or a stock journal.'),
                ],
            ],
            'opening_stock' => [
                'title' => __('Opening Stock'),
                'summary' => __('Go-live balances: stock in only, no money, no supplier bill. Use once when you start the shop books or bring an old balance into this system.'),
                'do' => [
                    __('Enter as-on qty for each product / batch at start.'),
                    __('Use Bulk update for many items; Add one for a single line.'),
                ],
                'dont' => [
                    __('Do not use Opening for normal purchases — use Purchase.'),
                    __('Do not use Opening to correct a wrong count — use Physical Stock.'),
                    __('Do not enter the same opening twice (qty will double).'),
                ],
                'tip' => __('Default date is store / FY open date when set.'),
            ],
            'purchase' => [
                'title' => __('Purchase bill'),
                'summary' => __('Receive goods: stock in + supplier balance. Choose Credit (pay later) or Cash / paid now and pick how you paid (Cash, Bank, UPI).'),
                'do' => [
                    __('Enter supplier bill with batch, qty, rate, GST.'),
                    __('Pick Credit or Cash before save; for Cash select the payment account.'),
                    __('Convert from Purchase Order when the order already exists.'),
                ],
                'dont' => [
                    __('Do not use Purchase for opening balances — use Opening Stock.'),
                    __('Do not leave Cash selected without a payment account.'),
                ],
            ],
            'put_away' => [
                'title' => __('Put Away'),
                'summary' => __('After receive, assign each batch to a rack / aisle. Quantity stays on the place (godown); rack is location only.'),
                'do' => [
                    __('Filter “Not placed” and set rack for new batches.'),
                    __('Manage racks first if the list is empty.'),
                ],
                'dont' => [
                    __('Do not use Put Away to move qty between godowns — use Stock Transfer.'),
                    __('Clearing a rack does not remove stock — only the location label.'),
                ],
            ],
            'stock_transfer' => [
                'title' => __('Stock Transfer'),
                'summary' => __('Move stock from one place (godown) to another. Posts OUT at From and IN at To in the same stock log — no money.'),
                'do' => [
                    __('Pick From place and To place (must differ).'),
                    __('Enter product, batch and qty leaving the From place.'),
                    __('Use Edit / Cancel only with permission — cancel reverses both sides.'),
                ],
                'dont' => [
                    __('Do not use Transfer for a sale or purchase.'),
                    __('Do not use Transfer to write off damage — use Rejection.'),
                    __('Do not pick the same place as From and To.'),
                ],
            ],
            'stock_journal' => [
                'title' => __('Stock Journal'),
                'summary' => __('Consume (out) and produce (in) in one voucher — stock only, no money. Typical for packing, mixing, or converting packs.'),
                'do' => [
                    __('Add Out lines for what you consume; In lines for what you produce.'),
                    __('Use the same place unless you intentionally change godown on a line.'),
                ],
                'dont' => [
                    __('Do not use Journal for supplier buys (Purchase) or customer sales (Sale).'),
                    __('Do not use Journal for a simple write-off — use Rejection.'),
                    __('Do not use Journal to move between places — use Stock Transfer.'),
                ],
            ],
            'stock_issue' => [
                'title' => __('Stock Issue'),
                'summary' => __('Issue stock out for use / demo / farm trial — stock out, no money. Unused goods come back later on Stock Return. Prefer Transfer when moving between shop places.'),
                'do' => [
                    __('Pick batch and qty leaving on-hand.'),
                    __('Note why in narration if useful.'),
                ],
                'dont' => [
                    __('Do not use Issue for a customer sale — use Sale invoice.'),
                    __('Do not use Issue for damaged goods write-off — use Rejection.'),
                    __('Do not use Issue to move stock to another godown — use Stock Transfer.'),
                ],
            ],
            'stock_return' => [
                'title' => __('Stock Return'),
                'summary' => __('Bring unused issued stock back in — stock in, no money. Prefer the same batch number when possible.'),
                'do' => [
                    __('Enter qty returning and batch.'),
                ],
                'dont' => [
                    __('Do not use for customer returns — use Credit note (sale return).'),
                    __('Do not use for supplier returns — use Purchase return.'),
                ],
            ],
            'stock_rejection' => [
                'title' => __('Stock Rejection'),
                'summary' => __('Write off damaged, expired, or rejected stock — stock out, no money. Line qty is written off.'),
                'do' => [
                    __('Select batch and qty to scrap.'),
                ],
                'dont' => [
                    __('Do not enter a physical count here — use Physical Stock (difference posts).'),
                    __('Do not use Rejection for goods going back to supplier — use Purchase return.'),
                ],
            ],
            'physical_stock' => [
                'title' => __('Physical Stock'),
                'summary' => __('Stock verification: enter counted qty. Only the difference (system vs counted) posts in or out. No money.'),
                'do' => [
                    __('Enter what you actually counted on the shelf.'),
                    __('Leave lines you did not count blank / skip them.'),
                ],
                'dont' => [
                    __('Do not enter the adjustment qty — enter the full counted qty.'),
                    __('Do not use Physical for opening go-live — use Opening Stock.'),
                ],
            ],
            'purchase_order' => [
                'title' => __('Purchase Order'),
                'summary' => __('Order goods from a supplier. No stock and no money until you Convert to Purchase. Uses Transaction Master series (PO).'),
                'do' => [
                    __('Save PO, amend while Open, print for the supplier.'),
                    __('When goods arrive: Convert to Purchase, add batches, then choose Credit or Cash payment.'),
                ],
                'dont' => [
                    __('Do not expect stock to increase on PO save — only on Purchase.'),
                    __('Do not cancel a converted PO — cancel the purchase instead if needed.'),
                ],
            ],
            'purchase_return' => [
                'title' => __('Purchase Return'),
                'summary' => __('Return goods to the supplier against a purchase bill — stock out + reduces supplier balance.'),
                'do' => [
                    __('Pick the original purchase, then return qty per line (capped by returnable).'),
                ],
                'dont' => [
                    __('Do not return more than was bought / already returned.'),
                    __('Do not use for customer credit notes — that is Sale return.'),
                ],
            ],
            'sale' => [
                'title' => __('Sale invoice'),
                'summary' => __('Sell to a customer: stock out + money (receivable / received). Pick batch from available stock.'),
                'do' => [
                    __('Select customer ledger (not walk-in).'),
                    __('Change GST on a line only when needed — you will be asked to confirm.'),
                    __('Use Save, or Save & Print to open the PDF after save.'),
                ],
                'dont' => [
                    __('Do not bill without stock — choose another batch or receive purchase first.'),
                    __('Do not use Sale to issue free samples with no invoice — use Stock Issue if stock-only.'),
                ],
            ],
        ];
    }
}
