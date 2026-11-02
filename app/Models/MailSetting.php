<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $guarded = [];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'password' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::create(['is_enabled' => false]);
    }
}
