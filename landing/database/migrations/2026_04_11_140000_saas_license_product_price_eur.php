<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saas_license_products', function (Blueprint $table): void {
            $table->unsignedInteger('price_eur_minor')->nullable()->after('price_usd_minor');
        });
    }

    public function down(): void
    {
        Schema::table('saas_license_products', function (Blueprint $table): void {
            $table->dropColumn('price_eur_minor');
        });
    }
};
