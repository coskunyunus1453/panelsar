<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plugin_migration_runs', function (Blueprint $table) {
            $table->foreignId('target_domain_id')
                ->nullable()
                ->after('plugin_module_id')
                ->constrained('domains')
                ->nullOnDelete();
            $table->index(['user_id', 'target_domain_id']);
        });
    }

    public function down(): void
    {
        Schema::table('plugin_migration_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_domain_id');
        });
    }
};
