<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('name_mr')->nullable();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->string('label_en');
            $table->string('label_mr')->nullable();
            $table->string('route_name')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('role_menu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_add')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->unique(['role_id', 'menu_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained('roles')->nullOnDelete();
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('locale', 5)->nullable();
            $table->boolean('is_active')->default(true);
        });

        // Per-user override: NULL = inherit from role, 1 = allow, 0 = deny.
        Schema::create('user_menu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_view')->nullable();
            $table->boolean('can_add')->nullable();
            $table->boolean('can_edit')->nullable();
            $table->boolean('can_delete')->nullable();
            $table->unique(['user_id', 'menu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_menu');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['phone', 'locale', 'is_active']);
        });
        Schema::dropIfExists('role_menu');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('roles');
    }
};
