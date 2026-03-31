<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployment_configs', function (Blueprint $table) {
            $table->json('branch_whitelist')->nullable()->after('branch');
        });
    }

    public function down(): void
    {
        Schema::table('deployment_configs', function (Blueprint $table) {
            $table->dropColumn('branch_whitelist');
        });
    }
};
