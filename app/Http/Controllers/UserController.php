<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForDrawer;
use App\Models\Godown;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Models\UserMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    use RespondsForDrawer;

    public function index(Request $request)
    {
        $rows = User::with(['role', 'godowns'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('email', 'like', $t)->orWhere('phone', 'like', $t));
            })
            ->when($request->filled('role_id'), fn ($q) => $q->where('role_id', $request->role_id))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('users.index', ['rows' => $rows, 'roles' => Role::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('users._drawer', $this->formData(new User(['is_active' => true])));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request);
        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create($data + ['email_verified_at' => now()]);
            $this->syncRelations($user, $request);

            return $user;
        });

        return $this->savedResponse($request, __('User saved.'), 'users.index');
    }

    public function edit(User $user)
    {
        return view('users._drawer', $this->formData($user));
    }

    public function update(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request, $user);
        if ($user->id === $request->user()->id) {
            $data['is_active'] = true; // cannot disable yourself
            $data['role_id'] = $user->role_id;
        }
        DB::transaction(function () use ($user, $data, $request) {
            $user->update($data);
            $this->syncRelations($user, $request);
        });

        return $this->savedResponse($request, __('User saved.'), 'users.index');
    }

    public function destroy(Request $request, User $user): JsonResponse|RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return $this->failedResponse($request, __('You cannot delete your own account.'));
        }
        if ($user->isOwner() && User::whereHas('role', fn ($q) => $q->where('slug', 'owner'))->count() <= 1) {
            return $this->failedResponse($request, __('The last owner cannot be deleted.'));
        }
        try {
            $user->delete();
        } catch (\Illuminate\Database\QueryException) {
            return $this->failedResponse($request, __('This record is in use and cannot be deleted. Deactivate it instead.'));
        }

        return $this->savedResponse($request, __('User deleted.'), 'users.index');
    }

    public function passwordForm(User $user)
    {
        return view('users._password-drawer', compact('user'));
    }

    public function setPassword(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $user->update(['password' => $data['password']]);

        return $this->savedResponse($request, __('Password updated.'), 'users.index');
    }

    public function toggleStatus(Request $request, User $user): JsonResponse|RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return $this->failedResponse($request, __('You cannot disable your own account.'));
        }
        if ($user->isOwner() && $user->is_active && User::whereHas('role', fn ($q) => $q->where('slug', 'owner'))->where('is_active', true)->count() <= 1) {
            return $this->failedResponse($request, __('The last owner cannot be disabled.'));
        }

        $user->update(['is_active' => ! $user->is_active]);

        return $this->savedResponse(
            $request,
            $user->is_active ? __('User activated.') : __('User deactivated.'),
            'users.index'
        );
    }

    private function formData(User $user): array
    {
        $leafMenus = Menu::with(['parent.parent'])
            ->whereNotNull('route_name')
            ->where('is_active', true)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get();

        return [
            'user' => $user,
            'roles' => Role::orderBy('id')->get(),
            'godowns' => Godown::where('is_active', true)->orderBy('name')->get(),
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
            'overrides' => $user->exists ? UserMenu::where('user_id', $user->id)->get()->keyBy('menu_id') : collect(),
            'selectedGodowns' => $user->exists ? $user->godowns()->pluck('godowns.id')->all() : [],
        ];
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'.($user ? ','.$user->id : '')],
            'phone' => ['nullable', 'string', 'max:20'],
            'role_id' => ['required', 'exists:roles,id'],
            'locale' => ['nullable', 'in:en,mr'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        if ($user && blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }

    private function syncRelations(User $user, Request $request): void
    {
        $user->godowns()->sync(array_map('intval', (array) $request->input('godown_ids', [])));

        // Per-user overrides: '' = inherit from role, '1' = allow, '0' = deny.
        UserMenu::where('user_id', $user->id)->delete();
        foreach ((array) $request->input('ovr', []) as $menuId => $actions) {
            $row = [];
            foreach (['view', 'add', 'edit', 'delete'] as $a) {
                $v = $actions[$a] ?? '';
                $row['can_'.$a] = $v === '' ? null : (bool) (int) $v;
            }
            if (count(array_filter($row, fn ($v) => $v !== null)) > 0) {
                UserMenu::create($row + ['user_id' => $user->id, 'menu_id' => (int) $menuId]);
            }
        }
    }
}
