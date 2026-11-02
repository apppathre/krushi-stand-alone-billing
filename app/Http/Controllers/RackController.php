<?php

namespace App\Http\Controllers;

use App\Models\Godown;
use App\Models\Rack;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RackController extends MasterController
{
    protected function config(): array
    {
        $godowns = Godown::query()->where('is_active', true)->orderBy('name')->get()
            ->mapWithKeys(fn (Godown $g) => [$g->id => $g->display_name])->all();

        return [
            'model' => Rack::class,
            'route' => 'racks',
            'menu' => 'racks',
            'title' => 'Racks / Locations',
            'singular' => 'Rack',
            'search' => ['code', 'name', 'name_mr', 'aisle'],
            'order' => 'code',
            'columns' => [
                ['name' => 'godown_label', 'label' => 'Place'],
                ['name' => 'code', 'label' => 'Code'],
                ['name' => 'display_name', 'label' => 'Name'],
                ['name' => 'aisle', 'label' => 'Aisle'],
                ['name' => 'default_label', 'label' => 'Default'],
            ],
            'fields' => [
                ['name' => 'godown_id', 'label' => 'Place / Godown', 'type' => 'select', 'required' => true, 'options' => $godowns],
                ['name' => 'code', 'label' => 'Code', 'required' => true, 'hint' => 'e.g. A-01, R2-B'],
                ['name' => 'name', 'label' => 'Name (English)', 'required' => true],
                ['name' => 'name_mr', 'label' => 'Name (Marathi)'],
                ['name' => 'aisle', 'label' => 'Aisle / Row'],
                ['name' => 'notes', 'label' => 'Notes'],
                ['name' => 'is_default', 'label' => 'Default location for this place (auto on Purchase)', 'type' => 'checkbox'],
                ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox'],
            ],
            'rules' => fn ($id) => [
                'godown_id' => ['required', 'exists:godowns,id'],
                'code' => [
                    'required', 'string', 'max:30',
                    Rule::unique('racks', 'code')
                        ->where(fn ($q) => $q->where('godown_id', request('godown_id')))
                        ->ignore($id),
                ],
                'name' => ['required', 'string', 'max:255'],
                'name_mr' => ['nullable', 'string', 'max:255'],
                'aisle' => ['nullable', 'string', 'max:40'],
                'notes' => ['nullable', 'string', 'max:255'],
            ],
        ];
    }

    public function store(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $c = $this->config();
        $data = $this->payload($request, $c);
        $item = $c['model']::create($data);
        $this->syncDefaultLocation($item, $data);

        return $this->savedResponse($request, __(':item saved.', ['item' => __($c['singular'])]), $c['route'].'.index');
    }

    public function update(\Illuminate\Http\Request $request, string $id): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $c = $this->config();
        $item = $c['model']::findOrFail($id);
        $data = $this->payload($request, $c, (int) $id);
        $item->update($data);
        $this->syncDefaultLocation($item->fresh(), $data);

        return $this->savedResponse($request, __(':item saved.', ['item' => __($c['singular'])]), $c['route'].'.index');
    }

    private function syncDefaultLocation(Rack $item, array $data): void
    {
        if (! empty($data['is_default'])) {
            Rack::query()
                ->where('godown_id', $item->godown_id)
                ->where('id', '!=', $item->id)
                ->update(['is_default' => false]);
            $item->update(['is_default' => true]);
            Godown::whereKey($item->godown_id)->update(['default_rack_id' => $item->id]);

            return;
        }

        if ((int) Godown::whereKey($item->godown_id)->value('default_rack_id') === (int) $item->id) {
            Godown::whereKey($item->godown_id)->update(['default_rack_id' => null]);
        }
    }

    public function index(Request $request): View
    {
        $c = $this->config();
        $rows = Rack::query()
            ->with('godown')
            ->when($request->filled('q'), function ($q) use ($request, $c) {
                $term = '%'.$request->q.'%';
                $q->where(function ($w) use ($term, $c) {
                    foreach ($c['search'] as $col) {
                        $w->orWhere($col, 'like', $term);
                    }
                });
            })
            ->when($request->filled('godown_id'), fn ($q) => $q->where('godown_id', $request->godown_id))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy('godown_id')->orderBy('code')
            ->paginate(20)->withQueryString();

        return view('masters.index', compact('c', 'rows'));
    }
}
