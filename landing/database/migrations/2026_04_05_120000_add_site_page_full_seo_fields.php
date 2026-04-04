<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->string('canonical_url', 2048)->nullable()->after('meta_title');
            $table->string('og_image', 2048)->nullable()->after('canonical_url');
            $table->string('robots', 64)->nullable()->after('og_image');
        });

        Schema::table('site_pages', function (Blueprint $table) {
            $table->text('meta_description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->dropColumn(['canonical_url', 'og_image', 'robots']);
        });

        Schema::table('site_pages', function (Blueprint $table) {
            $table->string('meta_description')->nullable()->change();
        });
    }
};
