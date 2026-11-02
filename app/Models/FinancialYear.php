<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class FinancialYear extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function documentSeries(): HasMany
    {
        return $this->hasMany(DocumentSeries::class);
    }

    public function contains(Carbon|string|null $date): bool
    {
        $date = $date ? Carbon::parse($date) : now();

        return $date->betweenIncluded($this->start_date, $this->end_date);
    }

    /** FY covering the date, if configured. */
    public static function covering(Carbon|string|null $date = null): ?self
    {
        $date = $date ? Carbon::parse($date) : now();

        return static::whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->first();
    }

    /**
     * Find the financial year containing the date.
     * Creates a standard April–March year only when none exists (seed / legacy safety).
     * Prefer managing years via the Financial Year master.
     */
    public static function forDate(Carbon|string|null $date = null): self
    {
        $date = $date ? Carbon::parse($date) : now();
        if ($found = static::covering($date)) {
            return $found;
        }

        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return static::create([
            'name' => $startYear.'-'.substr((string) ($startYear + 1), -2),
            'start_date' => Carbon::create($startYear, 4, 1)->toDateString(),
            'end_date' => Carbon::create($startYear + 1, 3, 31)->toDateString(),
            'is_current' => ! static::where('is_current', true)->exists(),
        ]);
    }

    protected static ?self $currentMemo = null;

    public static function current(): self
    {
        if (static::$currentMemo) {
            return static::$currentMemo;
        }

        return static::$currentMemo = static::where('is_current', true)->first() ?? static::forDate(now());
    }

    public static function forgetCurrent(): void
    {
        static::$currentMemo = null;
    }
}
