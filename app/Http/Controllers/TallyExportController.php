<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\FinancialYear;
use App\Models\Ledger;
use App\Models\LedgerEntry;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\ExcelExportService;
use App\Services\TallyExportService;
use App\Services\TallyImportService;
use App\Support\ExportCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class TallyExportController extends Controller
{
    public function __construct(
        private TallyExportService $tally,
        private ExcelExportService $excel,
        private TallyImportService $import,
    ) {}

    public function index(Request $request)
    {
        $currentFy = FinancialYear::query()->where('is_current', true)->first()
            ?? FinancialYear::query()->orderByDesc('start_date')->first();

        $from = $request->input('from', $currentFy?->start_date?->toDateString() ?: now()->startOfMonth()->toDateString());
        $to = $request->input('to', min(
            now()->toDateString(),
            $currentFy?->end_date?->toDateString() ?: now()->toDateString()
        ));

        $includes = ExportCatalog::includes();
        $selected = $request->input('include');
        if (! is_array($selected) || $selected === []) {
            $selected = ExportCatalog::defaultIncludeKeys();
        }

        return view('reports.tally-export', [
            'tab' => $request->input('tab', 'export'),
            'from' => $from,
            'to' => $to,
            'edition' => $request->input('edition', 'prime'),
            'format' => $request->input('format', 'xml'),
            'scope' => $request->input('scope', 'both'),
            'companyName' => CompanyProfile::current()->display_name,
            'catalog' => $includes,
            'selected' => $selected,
            'excluded' => ExportCatalog::excluded(),
            'importCatalog' => ExportCatalog::importIncludes(),
            'importExcluded' => ExportCatalog::importExcluded(),
            'counts' => $this->previewCounts($from, $to),
            'importResult' => session('tally_import_result'),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canMenu('report_tally_export', 'add')
            || $request->user()->canMenu('report_tally_export', 'edit')
            || $request->user()->canMenu('report_tally_export', 'view'), 403);

        $allowed = array_keys(ExportCatalog::importIncludes());
        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'include' => ['required', 'array', 'min:1'],
            'include.*' => ['string', Rule::in($allowed)],
        ]);

        $upload = $data['file'];
        $ext = strtolower($upload->getClientOriginalExtension() ?: '');
        if (! in_array($ext, ['xml', 'txt'], true)) {
            return back()->withErrors(['file' => __('Upload a Tally XML (.xml) file.')]);
        }

        $xml = file_get_contents($upload->getRealPath());
        if ($xml === false || trim($xml) === '') {
            return back()->withErrors(['file' => __('Could not read the uploaded file.')]);
        }

        try {
            $result = $this->import->importXml($xml, $data['include'], $request->user()->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('reports.tally-export', ['tab' => 'import'])
            ->with('status', __('Tally XML import finished.'))
            ->with('tally_import_result', $result);
    }

    public function download(Request $request): Response
    {
        $allowedKeys = array_keys(ExportCatalog::includes());
        $data = $request->validate([
            'format' => ['required', 'in:xml,xls'],
            'edition' => ['required_if:format,xml', 'nullable', 'in:prime,erp9'],
            'scope' => ['nullable', 'in:masters,vouchers,both'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'include' => ['required', 'array', 'min:1'],
            'include.*' => ['string', Rule::in($allowedKeys)],
        ]);

        $include = array_values(array_unique($data['include']));
        $scope = $data['scope'] ?? 'both';

        if ($data['format'] === 'xls') {
            $body = $this->excel->exportXls($data['from'], $data['to'], $include);
            $file = $this->excel->suggestedFilename($data['from'], $data['to']);

            return response($body, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$file.'"',
            ]);
        }

        $edition = $data['edition'] ?? 'prime';
        $xml = $this->tally->exportXml($edition, $scope, $data['from'], $data['to'], null, $include);
        $file = $this->tally->suggestedFilename($edition, $scope, $data['from'], $data['to']);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$file.'"',
        ]);
    }

    /** Live counts for the interactive preview strip. */
    private function previewCounts(string $from, string $to): array
    {
        return [
            'ledgers' => Ledger::query()->where('is_active', true)->count(),
            'sales' => Sale::query()->where('status', 'active')->whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $to)->count(),
            'purchases' => Purchase::query()->where('status', 'active')->whereDate('purchase_date', '>=', $from)->whereDate('purchase_date', '<=', $to)->count(),
            'vouchers' => LedgerEntry::query()
                ->whereDate('entry_date', '>=', $from)
                ->whereDate('entry_date', '<=', $to)
                ->selectRaw('COUNT(DISTINCT CONCAT(voucher_type, "-", voucher_id)) as c')
                ->value('c') ?? 0,
        ];
    }
}
