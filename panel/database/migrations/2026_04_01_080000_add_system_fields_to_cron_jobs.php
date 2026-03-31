<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cron_jobs', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('engine_job_id');
            $table->string('system_key', 120)->nullable()->after('is_system');
            $table->index('is_system');
            $table->unique('system_key');
        });
    }

    public function down(): void
    {
        Schema::table('cron_jobs', function (Blueprint $table) {
            $table->dropUnique(['system_key']);
            $table->dropIndex(['is_system']);
            $table->dropColumn(['is_system', 'system_key']);
        });
    }
};

