<?php

namespace App\Models\Concerns;

trait LocalizedName
{
    /** Name in the active UI locale, falling back to English. */
    public function getDisplayNameAttribute(): string
    {
        if (app()->getLocale() === 'mr' && filled($this->name_mr)) {
            return $this->name_mr;
        }

        return (string) $this->name;
    }
}
