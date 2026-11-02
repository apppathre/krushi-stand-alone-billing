<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_series', function (Blueprint $table) {
            $table->string('name', 80)->nullable()->after('doc_type');
            $table->boolean('is_default')->default(true)->after('padding');
            $table->boolean('is_active')->default(true)->after('is_default');
        });

        // Allow multiple formats per doc_type + FY (TMS-style).
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            // SQLite: rebuild uniqueness via index drop if present.
            Schema::table('document_series', function (Blueprint $table) {
                $table->dropUnique(['doc_type', 'financial_year_id']);
            });
        } else {
            Schema::table('document_series', function (Blueprint $table) {
                $table->dropUnique(['doc_type', 'financial_year_id']);
            });
        }

        Schema::table('document_series', function (Blueprint $table) {
            $table->index(['financial_year_id', 'doc_type'], 'document_series_fy_type_idx');
        });

        foreach (DB::table('document_series')->orderBy('id')->get() as $row) {
            $label = config('transaction_types.types.'.$row->doc_type.'.label')
                ?? ucfirst(str_replace('_', ' ', (string) $row->doc_type));
            DB::table('document_series')->where('id', $row->id)->update([
                'name' => $row->name ?: $label,
                'is_default' => true,
                'is_active' => true,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('packing', 120)->nullable()->after('unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('packing');
        });

        Schema::table('document_series', function (Blueprint $table) {
            $table->dropIndex('document_series_fy_type_idx');
            $table->dropColumn(['name', 'is_default', 'is_active']);
            $table->unique(['doc_type', 'financial_year_id']);
        });
    }
};
