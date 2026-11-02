<?php

namespace App\Models;

use App\Models\Concerns\LocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use LocalizedName;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RoleMenu::class);
    }
}
