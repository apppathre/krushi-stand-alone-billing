<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForDrawer;
use App\Models\Menu;
use App\Models\Role;
use App\Models\RoleMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    use RespondsForDrawer;

    public function index()
    {
        return view('roles.index', ['roles' => Role::withCount('users')->orderBy('id')->get()]);
    }

    public function create()
    {
        return view('roles._drawer', $this->formData(new Role()));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
            'name_mr' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $role = Role::create($data + ['slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4))]);
        $this->syncPermissions($role, $request);

        return $this->savedResponse($request, __('Role saved.'), 'roles.index');
    }

    public function edit(Role $role)
    {
        return view('roles._drawer', $this->formData($role));
    }

    public function update(Request $request, Role $role): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name,'.$role->id],
            'name_mr' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $role->update($data);
        if ($role->slug !== 'owner') {
            $this->syncPermissions($role, $request);
        }

        return $this->savedResponse($request, __('Role saved.'), 'roles.index');
    }

    public function destroy(Request $request, Role $role): JsonResponse|RedirectResponse
    {
        if ($role->is_system || $role->users()->exists()) {
            return $this->failedResponse($request, __('System roles and roles with users cannot be deleted.'));
        }
        $role->delete();

        return $this->savedResponse($request, __('Role deleted.'), 'roles.index');
    }

    private function formData(Role $role): array
    {
        $leafMenus = Menu::with(['parent.parent'])
            ->whereNotNull('route_name')
            ->where('is_active', true)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get();

        return [
            'role' => $role,
            'menuGroups' => $leafMenus->groupBy(function (Menu $m) {
                $section = $m->parent;
                if (! $section) {
                    return __('Other');
                }
                $top = $section->parent;

                return $top
                    ? $top->label.' · '.$section->label
                    : $section->label;
            }),
            'grants' => $role->exists ? RoleMenu::where('role_id', $role->id)->get()->keyBy('menu_id') : collect(),
        ];
    }

    private function syncPermissions(Role $role, Request $request): void
    {
        $input = (array) $request->input('perm', []);
        DB::transaction(function () use ($role, $input) {
            RoleMenu::where('role_id', $role->id)->delete();
            foreach (Menu::whereNotNull('route_name')->pluck('id') as $menuId) {
                $p = $input[$menuId] ?? [];
                $row = [
                    'can_view' => ! empty($p['view']) || ! empty($p['add']) || ! empty($p['edit']) || ! empty($p['delete']),
                    'can_add' => ! empty($p['add']),
                    'can_edit' => ! empty($p['edit']),
                    'can_delete' => ! empty($p['delete']),
                ];
                if (in_array(true, $row, true)) {
                    RoleMenu::create($row + ['role_id' => $role->id, 'menu_id' => $menuId]);
                }
            }
        });
    }
}
