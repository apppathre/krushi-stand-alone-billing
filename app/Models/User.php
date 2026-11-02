<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'avatar_path', 'password', 'role_id', 'locale', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** Cached per-request permission map: menu key => [view, add, edit, delete]. */
    protected ?Collection $permissionCache = null;

    protected ?Collection $godownCache = null;

    protected ?Collection $activeMenusCache = null;

    protected ?Collection $shellMenusCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function godowns(): BelongsToMany
    {
        return $this->belongsToMany(Godown::class, 'user_godowns');
    }

    public function menuOverrides(): HasMany
    {
        return $this->hasMany(UserMenu::class);
    }

    public function isOwner(): bool
    {
        $this->loadMissing('role');

        return $this->role?->slug === 'owner';
    }

    /** Godowns this user may work in (owners: all active godowns). Request-memoized. */
    public function accessibleGodowns(): Collection
    {
        if ($this->godownCache !== null) {
            return $this->godownCache;
        }

        if ($this->isOwner()) {
            return $this->godownCache = Godown::where('is_active', true)->orderBy('name')->get(['id', 'name', 'name_mr', 'code', 'is_active']);
        }

        return $this->godownCache = $this->godowns()->where('is_active', true)->orderBy('name')
            ->get(['godowns.id', 'godowns.name', 'godowns.name_mr', 'godowns.code', 'godowns.is_active']);
    }

    public function accessibleGodownIds(): array
    {
        return $this->accessibleGodowns()->pluck('id')->all();
    }

    /** Active menus (shared cache across requests; invalidated when menus change). */
    public function activeMenus(): Collection
    {
        if ($this->activeMenusCache !== null) {
            return $this->activeMenusCache;
        }

        return $this->activeMenusCache = Cache::remember('menus.active.flat', 300, function () {
            return Menu::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'key', 'parent_id', 'label_en', 'label_mr', 'route_name', 'icon', 'sort_order', 'is_active']);
        });
    }

    public static function forgetMenuCache(): void
    {
        Cache::forget('menus.active.flat');
    }

    /**
     * Effective permissions for every active menu: role grants merged with per-user overrides.
     *
     * @return Collection<string, array{view: bool, add: bool, edit: bool, delete: bool}>
     */
    public function menuPermissions(): Collection
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        $this->loadMissing('role');
        $menus = $this->activeMenus();
        $owner = $this->isOwner();
        $roleRows = (! $owner && $this->role_id)
            ? RoleMenu::where('role_id', $this->role_id)->get()->keyBy('menu_id')
            : collect();
        $overrides = (! $owner)
            ? $this->menuOverrides()->get()->keyBy('menu_id')
            : collect();

        $map = collect();
        foreach ($menus as $menu) {
            $perm = [];
            foreach (['view', 'add', 'edit', 'delete'] as $action) {
                $col = 'can_'.$action;
                if ($owner) {
                    $perm[$action] = true;

                    continue;
                }
                $allowed = (bool) ($roleRows[$menu->id]->$col ?? false);
                $override = $overrides[$menu->id]->$col ?? null;
                $perm[$action] = $override === null ? $allowed : (bool) $override;
            }
            $map[$menu->key] = $perm;
        }

        return $this->permissionCache = $map;
    }

    /**
     * Nested menu tree for the sidebar, filtered by this user's view rights.
     * Built in memory from the flat menu cache (no nested menu queries).
     */
    public function shellMenuTree(): Collection
    {
        if ($this->shellMenusCache !== null) {
            return $this->shellMenusCache;
        }

        $perms = $this->menuPermissions();
        $all = $this->activeMenus();
        $byParent = $all->groupBy(fn (Menu $m) => $m->parent_id === null ? 0 : (int) $m->parent_id);

        $build = function ($parentId) use (&$build, $byParent, $perms): Collection {
            return ($byParent[$parentId] ?? collect())->map(function (Menu $menu) use (&$build, $perms) {
                $clone = $menu->replicate();
                $clone->id = $menu->id;
                $clone->exists = true;
                $canView = (bool) data_get($perms, $menu->key.'.view', false);

                if ($menu->route_name) {
                    return $canView ? $clone : null;
                }

                $kids = $build($menu->id)->filter()->values();
                $clone->setRelation('children', $kids);

                return $kids->isNotEmpty() ? $clone : null;
            })->filter()->values();
        };

        return $this->shellMenusCache = $build(0);
    }

    public function canMenu(string $key, string $action = 'view'): bool
    {
        return (bool) ($this->menuPermissions()[$key][$action] ?? false);
    }

    public function initials(): string
    {
        $name = trim((string) $this->name);

        return mb_strtoupper(mb_substr($name !== '' ? $name : 'U', 0, 1));
    }

    public function avatarUrl(): ?string
    {
        $rel = ltrim(str_replace('\\', '/', (string) $this->avatar_path), '/');
        if ($rel === '' || ! is_file(public_path($rel))) {
            return null;
        }

        return asset($rel).'?v='.@filemtime(public_path($rel));
    }
}
