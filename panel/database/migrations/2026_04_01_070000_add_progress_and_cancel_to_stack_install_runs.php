<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stack_install_runs', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress')->default(0)->after('status');
            $table->boolean('cancel_requested')->default(false)->after('progress');
        });
    }

    public function down(): void
    {
        Schema::table('stack_install_runs', function (Blueprint $table) {
            $table->dropColumn(['progress', 'cancel_requested']);
        });
    }
};
