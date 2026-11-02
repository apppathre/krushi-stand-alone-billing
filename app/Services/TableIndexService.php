<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TableIndexService
{
    /**
     * @return list<array{table: string, name: string, columns: list<string>, why: string, present: bool, skipped: bool}>
     */
    public function status(): array
    {
        $rows = [];
        foreach ($this->catalog() as $index) {
            $table = $index['table'];
            $exists = Schema::hasTable($table);
            $rows[] = [
                'table' => $table,
                'name' => $index['name'],
                'columns' => $index['columns'],
                'why' => $index['why'],
                'present' => $exists && $this->hasIndex($table, $index['name']),
                'skipped' => ! $exists,
            ];
        }

        return $rows;
    }

    /**
     * Create any missing indexes. Safe to run again — existing ones are skipped.
     *
     * @return array{created: int, skipped: int, analyzed: int, rows: list<array<string, mixed>>}
     */
    public function apply(): array
    {
        $created = 0;
        $skipped = 0;
        $touched = [];
        $rows = [];
        foreach ($this->catalog() as $index) {
            $table = $index['table'];
            if (! Schema::hasTable($table)) {
                $skipped++;
                $rows[] = $index + ['result' => 'no table'];
                continue;
            }
            if ($this->hasIndex($table, $index['name'])) {
                $skipped++;
                $rows[] = $index + ['result' => 'already there'];
                continue;
            }
            if (! $this->columnsExist($table, $index['columns'])) {
                $skipped++;
                $rows[] = $index + ['result' => 'column missing'];
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->index($index['columns'], $index['name']);
            });
            $created++;
            $touched[$table] = true;
            $rows[] = $index + ['result' => 'created'];
        }
        $analyzed = $this->analyze(array_keys($touched));

        return [
            'created' => $created,
            'skipped' => $skipped,
            'analyzed' => $analyzed,
            'rows' => $rows,
        ];
    }

    /** @return list<array{table: string, name: string, columns: list<string>, why: string}> */
    public function catalog(): array
    {
        return config('table_indexes', []);
    }

    /** @param  list<string>  $tables */
    private function analyze(array $tables): int
    {
        if ($tables === []) {
            return 0;
        }
        $driver = Schema::getConnection()->getDriverName();
        $done = 0;
        try {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                foreach ($tables as $table) {
                    DB::statement('ANALYZE TABLE `'.str_replace('`', '', $table).'`');
                    $done++;
                }
            } elseif ($driver === 'sqlite') {
                DB::statement('ANALYZE');
                $done = count($tables);
            }
        } catch (\Throwable) {
        }

        return $done;
    }

    /** @param  list<string>  $columns */
    private function columnsExist(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    public function hasIndex(string $table, string $name): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        try {
            $indexes = collect(Schema::getIndexes($table))->pluck('name')->all();

            return in_array($name, $indexes, true);
        } catch (\Throwable) {
            return false;
        }
    }
}
