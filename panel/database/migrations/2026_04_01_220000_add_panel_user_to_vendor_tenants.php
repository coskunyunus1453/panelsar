<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendor_tenants', 'panel_user_id')) {
                $table->foreignId('panel_user_id')->nullable()->after('slug')->constrained('users')->nullOnDelete()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('vendor_tenants', 'panel_user_id')) {
                $table->dropConstrainedForeignId('panel_user_id');
            }
        });
    }
};
