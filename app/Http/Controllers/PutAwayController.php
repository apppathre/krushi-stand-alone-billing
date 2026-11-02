<?php

namespace App\Http\Controllers;

use App\Models\ProductBatch;
use App\Models\Rack;
use App\Support\StockQty;
use App\Support\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * After receive: assign rack / aisle location on batches (placement only — qty stays in stock_movements).
 */
class PutAwayController extends Controller
{
    public function index(Request $request): View
    {
        $placeId = WorkContext::placeId($request);
        $scope = $this->godownScope($request);
        $balances = StockQty::batchBalances($placeId);
        $inStockIds = array_keys(array_filter($balances, fn ($q) => $q > 0.0005));

        $rows = ProductBatch::query()
            ->with(['product.unit', 'godown', 'rack'])
            ->where('godown_id', $placeId)
            ->whereIn('godown_id', $scope)
            ->when($inStockIds !== [], fn ($q) => $q->whereIn('id', $inStockIds), fn ($q) => $q->whereRaw('0=1'))
            ->when($request->input('placed') === 'no', fn ($q) => $q->whereNull('rack_id'))
            ->when($request->input('placed') === 'yes', fn ($q) => $q->whereNotNull('rack_id'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('batch_no', 'like', $t)
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $t)->orWhere('code', 'like', $t)));
            })
            ->orderBy('product_id')->orderBy('batch_no')
            ->paginate(30)->withQueryString();

        $rows->getCollection()->transform(function (ProductBatch $b) use ($balances) {
            $b->setAttribute('qty_live', (float) ($balances[$b->id] ?? 0));

            return $b;
        });

        return view('put-away.index', [
            'rows' => $rows,
            'places' => $request->user()->accessibleGodowns(),
            'placeId' => $placeId,
        ]);
    }

    public function edit(Request $request, ProductBatch $batch): View
    {
        $this->authorizeGodown($request, $batch->godown_id);
        $batch->load(['product.unit', 'godown', 'rack']);
        $racks = Rack::query()
            ->where('godown_id', $batch->godown_id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
        $qty = StockQty::batchOnHand($batch->id);

        return view('put-away._drawer', compact('batch', 'racks', 'qty'));
    }

    public function update(Request $request, ProductBatch $batch): JsonResponse|RedirectResponse
    {
        $this->authorizeGodown($request, $batch->godown_id);
        $data = $request->validate([
            'rack_id' => [
                'nullable',
                Rule::exists('racks', 'id')->where(fn ($q) => $q->where('godown_id', $batch->godown_id)->where('is_active', true)),
            ],
        ]);

        $batch->update(['rack_id' => $data['rack_id'] ?: null]);

        if ($request->expectsJson() || $request->header('X-UI-Drawer') === '1' || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => __('Location saved.'), 'reload' => true]);
        }

        return redirect()->route('put-away.index')->with('status', __('Location saved.'));
    }
}
