<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Generic CRUD for simple master tables (manufacturers, categories, units, godowns).
 * Create/edit open in an AJAX side drawer — no full-page navigation.
 */
abstract class MasterController extends Controller
{
    /**
     * @return array{model: class-string<Model>, route: string, menu: string, title: string, singular: string, search: array, columns: array, fields: array, rules: array}
     */
    abstract protected function config(): array;

    public function index(Request $request): View
    {
        $c = $this->config();
        $rows = $c['model']::query()
            ->when($request->filled('q'), function ($q) use ($request, $c) {
                $term = '%'.$request->q.'%';
                $q->where(function ($w) use ($term, $c) {
                    foreach ($c['search'] as $col) {
                        $w->orWhere($col, 'like', $term);
                    }
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy($c['order'] ?? 'name')
            ->paginate(15)->withQueryString();

        return view('masters.index', compact('c', 'rows'));
    }

    public function create(Request $request): View
    {
        $c = $this->config();
        $item = new $c['model'](['is_active' => true]);

        if ($this->wantsDrawer($request)) {
            return view('masters._drawer', compact('c', 'item'));
        }

        // Fallback: still prefer drawer UX via index
        return view('masters._drawer', compact('c', 'item'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $c = $this->config();
        $c['model']::create($this->payload($request, $c));

        return $this->savedResponse($request, __(':item saved.', ['item' => __($c['singular'])]), $c['route'].'.index');
    }

    public function edit(Request $request, string $id): View
    {
        $c = $this->config();
        $item = $c['model']::findOrFail($id);

        return view('masters._drawer', compact('c', 'item'));
    }

    public function update(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $c = $this->config();
        $c['model']::findOrFail($id)->update($this->payload($request, $c, (int) $id));

        return $this->savedResponse($request, __(':item saved.', ['item' => __($c['singular'])]), $c['route'].'.index');
    }

    public function destroy(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $c = $this->config();
        try {
            $c['model']::findOrFail($id)->delete();
        } catch (QueryException) {
            if ($request->expectsJson() || $this->wantsDrawer($request)) {
                return response()->json(['message' => __('This record is in use and cannot be deleted. Deactivate it instead.')], 422);
            }

            return back()->withErrors(['delete' => __('This record is in use and cannot be deleted. Deactivate it instead.')]);
        }

        return $this->savedResponse($request, __(':item deleted.', ['item' => __($c['singular'])]), $c['route'].'.index');
    }

    protected function payload(Request $request, array $c, ?int $id = null): array
    {
        $rules = is_callable($c['rules']) ? ($c['rules'])($id) : $c['rules'];
        $data = $request->validate($rules);
        foreach ($c['fields'] as $f) {
            if (($f['type'] ?? 'text') === 'checkbox') {
                $data[$f['name']] = $request->boolean($f['name']);
            }
        }
        if (array_key_exists('short_code', $data)) {
            $data['short_code'] = filled($data['short_code'] ?? null) ? strtoupper(trim($data['short_code'])) : null;
        }

        return $data;
    }

    protected function wantsDrawer(Request $request): bool
    {
        return $request->header('X-UI-Drawer') === '1'
            || $request->boolean('drawer')
            || $request->ajax();
    }

    protected function savedResponse(Request $request, string $message, string $route): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $this->wantsDrawer($request)) {
            return response()->json(['ok' => true, 'message' => $message, 'reload' => true]);
        }

        return redirect()->route($route)->with('status', $message);
    }
}
