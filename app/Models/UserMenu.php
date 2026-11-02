<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMenu extends Model
{
    protected $table = 'user_menu';

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
