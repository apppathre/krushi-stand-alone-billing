<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForDrawer;
use App\Models\DocumentSeries;
use App\Models\FinancialYear;
use App\Services\FinancialYearService;
use App\Support\TransactionTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Transaction Master — multi-format number series per financial year (TMS style). */
class DocumentSeriesController extends Controller
{
    use RespondsForDrawer;

    public function __construct(private FinancialYearService $years) {}

    public function index(Request $request)
    {
        $years = FinancialYear::orderByDesc('start_date')->get();
        $fyId = (int) $request->input('financial_year_id', FinancialYear::current()->id);
        if (! $years->contains('id', $fyId)) {
            $fyId = (int) ($years->first()->id ?? 0);
        }
        $fy = $years->firstWhere('id', $fyId) ?? FinancialYear::current();
        $this->years->seedSeries($fy);

        $rows = DocumentSeries::where('financial_year_id', $fy->id)
            ->orderBy('doc_type')->orderByDesc('is_default')->orderBy('name')->get();

        $groupedRows = [];
        foreach (TransactionTypes::grouped() as $group => $types) {
            $items = [];
            foreach ($types as $t) {
                $formats = $rows->where('doc_type', $t['code'])->values();
                if ($formats->isEmpty()) {
                    continue;
                }
                $items[] = ['code' => $t['code'], 'meta' => $t['meta'], 'formats' => $formats];
            }
            if ($items !== []) {
                $groupedRows[$group] = $items;
            }
        }

        // Any leftover custom doc_types not in config
        $known = array_keys(TransactionTypes::all());
        $extra = $rows->whereNotIn('doc_type', $known)->groupBy('doc_type');
        if ($extra->isNotEmpty()) {
            $groupedRows['other'] = $extra->map(fn ($formats, $code) => [
                'code' => $code,
                'meta' => ['label' => $code, 'prefix' => $formats->first()->prefix],
                'formats' => $formats->values(),
            ])->values()->all();
        }

        return view('document-series.index', [
            'years' => $years,
            'fy' => $fy,
            'groupedRows' => $groupedRows,
            'labels' => TransactionTypes::labels(),
            'typeOptions' => TransactionTypes::labels(),
        ]);
    }

    public function create(Request $request)
    {
        $fy = FinancialYear::findOrFail((int) $request->input('financial_year_id', FinancialYear::current()->id));
        $docType = (string) $request->input('doc_type', 'sale');
        if (! isset(TransactionTypes::all()[$docType])) {
            $docType = 'sale';
        }
        $meta = TransactionTypes::all()[$docType];

        return view('document-series._drawer', [
            'series' => new DocumentSeries([
                'financial_year_id' => $fy->id,
                'doc_type' => $docType,
                'name' => $meta['label'],
                'prefix' => $meta['prefix'],
                'next_number' => 1,
                'padding' => 4,
                'is_default' => false,
                'is_active' => true,
            ]),
            'fy' => $fy,
            'labels' => TransactionTypes::labels(),
            'typeOptions' => TransactionTypes::labels(),
            'creating' => true,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request);
        $fyId = (int) $data['financial_year_id'];

        if (! empty($data['is_default'])) {
            DocumentSeries::where('financial_year_id', $fyId)
                ->where('doc_type', $data['doc_type'])
                ->update(['is_default' => false]);
        } else {
            // First format of a type becomes default automatically.
            $hasDefault = DocumentSeries::where('financial_year_id', $fyId)
                ->where('doc_type', $data['doc_type'])
                ->where('is_default', true)->exists();
            $data['is_default'] = ! $hasDefault;
        }

        DocumentSeries::create($data + ['is_active' => $data['is_active'] ?? true]);

        return $this->savedResponse($request, __('Transaction format saved.'), 'document-series.index');
    }

    public function edit(DocumentSeries $documentSeries)
    {
        return view('document-series._drawer', [
            'series' => $documentSeries->load('financialYear'),
            'fy' => $documentSeries->financialYear,
            'labels' => TransactionTypes::labels(),
            'typeOptions' => TransactionTypes::labels(),
            'creating' => false,
        ]);
    }

    public function update(Request $request, DocumentSeries $documentSeries): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request, $documentSeries);
        unset($data['financial_year_id'], $data['doc_type']); // lock type / year on edit

        if (! empty($data['is_default'])) {
            DocumentSeries::where('financial_year_id', $documentSeries->financial_year_id)
                ->where('doc_type', $documentSeries->doc_type)
                ->where('id', '!=', $documentSeries->id)
                ->update(['is_default' => false]);
            $data['is_default'] = true;
        }

        $documentSeries->update($data);

        return $this->savedResponse($request, __('Transaction series saved.'), 'document-series.index');
    }

    public function destroy(Request $request, DocumentSeries $documentSeries): JsonResponse|RedirectResponse
    {
        $wasDefault = $documentSeries->is_default;
        $fyId = $documentSeries->financial_year_id;
        $type = $documentSeries->doc_type;
        $documentSeries->delete();

        if ($wasDefault) {
            $next = DocumentSeries::where('financial_year_id', $fyId)->where('doc_type', $type)->orderBy('id')->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return $this->savedResponse($request, __('Transaction format deleted.'), 'document-series.index');
    }

    public function seed(Request $request): JsonResponse|RedirectResponse
    {
        $fy = FinancialYear::findOrFail((int) $request->input('financial_year_id', FinancialYear::current()->id));
        $added = $this->years->seedSeries($fy);

        return $this->savedResponse(
            $request,
            $added ? __(':count missing series added.', ['count' => $added]) : __('All series already present.'),
            'document-series.index'
        );
    }

    private function validated(Request $request, ?DocumentSeries $existing = null): array
    {
        $types = array_keys(TransactionTypes::all());

        $data = $request->validate([
            'financial_year_id' => [$existing ? 'nullable' : 'required', 'exists:financial_years,id'],
            'doc_type' => [$existing ? 'nullable' : 'required', Rule::in($types)],
            'name' => ['required', 'string', 'max:80'],
            'prefix' => ['required', 'string', 'max:20'],
            'next_number' => ['required', 'integer', 'min:1'],
            'padding' => ['required', 'integer', 'between:1,8'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
