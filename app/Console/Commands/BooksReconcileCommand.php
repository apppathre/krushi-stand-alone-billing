<?php

namespace App\Console\Commands;

use App\Models\LedgerEntry;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Services\StockService;
use App\Support\StockQty;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gate for stock + money books health.
 * Stock books = SUM(stock_movements); product_batches.qty_on_hand is a cache.
 * Money books = ledger_entries; every voucher_type+voucher_id group must Dr=Cr.
 */
class BooksReconcileCommand extends Command
{
    protected $signature = 'books:reconcile
                            {--fix : Sync drifted batch qty_on_hand from the movement log}
                            {--quiet-ok : Exit 0 with minimal output when clean}';

    protected $description = 'Check batch cache vs stock_movements and unbalanced ledger vouchers';

    public function handle(StockService $stock): int
    {
        StockQty::flush();

        $batchMismatches = $this->batchCacheMismatches();
        $unbalanced = $this->unbalancedVouchers();

        if ($batchMismatches === [] && $unbalanced === []) {
            if (! $this->option('quiet-ok')) {
                $this->info('Books OK — batch cache matches movements; all ledger vouchers balance.');
            }

            return self::SUCCESS;
        }

        if ($batchMismatches !== []) {
            $this->error(count($batchMismatches).' batch cache mismatch(es):');
            $this->table(
                ['batch_id', 'batch_no', 'cache', 'from_log', 'delta'],
                array_map(fn ($r) => [
                    $r['id'],
                    $r['batch_no'],
                    $r['cache'],
                    $r['log'],
                    $r['delta'],
                ], $batchMismatches)
            );

            if ($this->option('fix')) {
                foreach ($batchMismatches as $row) {
                    $batch = ProductBatch::find($row['id']);
                    if ($batch) {
                        $stock->syncBatchCache($batch);
                    }
                }
                $this->info('Synced '.count($batchMismatches).' batch cache row(s) from stock_movements.');
            }
        }

        if ($unbalanced !== []) {
            $this->error(count($unbalanced).' unbalanced ledger voucher(s):');
            $this->table(
                ['voucher_type', 'voucher_id', 'debit', 'credit', 'delta'],
                array_map(fn ($r) => [
                    $r['voucher_type'],
                    $r['voucher_id'],
                    $r['debit'],
                    $r['credit'],
                    $r['delta'],
                ], $unbalanced)
            );
        }

        // After --fix, re-check batch cache; money imbalances still fail.
        if ($this->option('fix')) {
            StockQty::flush();
            $batchMismatches = $this->batchCacheMismatches();
            if ($batchMismatches === [] && $unbalanced === []) {
                $this->info('Books OK after cache fix.');

                return self::SUCCESS;
            }
            if ($batchMismatches === [] && $unbalanced !== []) {
                return self::FAILURE;
            }
            if ($batchMismatches !== []) {
                $this->error('Batch cache still mismatched after --fix.');
            }
        }

        return self::FAILURE;
    }

    /** @return list<array{id:int, batch_no:string, cache:float, log:float, delta:float}> */
    private function batchCacheMismatches(): array
    {
        $logs = StockMovement::query()
            ->whereNotNull('batch_id')
            ->groupBy('batch_id')
            ->select('batch_id', DB::raw('ROUND(COALESCE(SUM(qty_in - qty_out), 0), 3) as on_hand'))
            ->pluck('on_hand', 'batch_id');

        $out = [];
        ProductBatch::query()->orderBy('id')->each(function (ProductBatch $batch) use ($logs, &$out) {
            $fromLog = round((float) ($logs[$batch->id] ?? 0), 3);
            $cache = round((float) $batch->qty_on_hand, 3);
            if (abs($cache - $fromLog) > 0.0005) {
                $out[] = [
                    'id' => $batch->id,
                    'batch_no' => (string) $batch->batch_no,
                    'cache' => $cache,
                    'log' => $fromLog,
                    'delta' => round($cache - $fromLog, 3),
                ];
            }
        });

        return $out;
    }

    /** @return list<array{voucher_type:string, voucher_id:int, debit:float, credit:float, delta:float}> */
    private function unbalancedVouchers(): array
    {
        return LedgerEntry::query()
            ->select('voucher_type', 'voucher_id')
            ->selectRaw('ROUND(COALESCE(SUM(debit), 0), 2) as debit')
            ->selectRaw('ROUND(COALESCE(SUM(credit), 0), 2) as credit')
            ->groupBy('voucher_type', 'voucher_id')
            ->havingRaw('ABS(SUM(debit) - SUM(credit)) > 0.01')
            ->orderBy('voucher_type')
            ->orderBy('voucher_id')
            ->get()
            ->map(fn ($r) => [
                'voucher_type' => (string) $r->voucher_type,
                'voucher_id' => (int) $r->voucher_id,
                'debit' => (float) $r->debit,
                'credit' => (float) $r->credit,
                'delta' => round((float) $r->debit - (float) $r->credit, 2),
            ])
            ->all();
    }
}
