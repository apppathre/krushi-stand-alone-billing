<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleMenu extends Model
{
    protected $table = 'role_menu';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'can_view' => 'boolean', 'can_add' => 'boolean',
            'can_edit' => 'boolean', 'can_delete' => 'boolean',
        ];
    }
}
