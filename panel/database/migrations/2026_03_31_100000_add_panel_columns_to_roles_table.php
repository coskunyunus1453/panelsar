<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('permission.table_names.roles');
        if (! is_string($table) || ! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->boolean('is_system')->default(false)->after('guard_name');
            $blueprint->boolean('assignable_by_reseller')->default(false)->after('is_system');
            $blueprint->string('display_name')->nullable()->after('assignable_by_reseller');
            $blueprint->foreignId('owner_user_id')->nullable()->after('display_name')->constrained('users')->nullOnDelete();
            $blueprint->index(['owner_user_id', 'assignable_by_reseller']);
        });
    }

    public function down(): void
    {
        $table = config('permission.table_names.roles');
        if (! is_string($table) || ! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropForeign(['owner_user_id']);
            $blueprint->dropIndex(['owner_user_id', 'assignable_by_reseller']);
            $blueprint->dropColumn(['is_system', 'assignable_by_reseller', 'display_name', 'owner_user_id']);
        });
    }
};
