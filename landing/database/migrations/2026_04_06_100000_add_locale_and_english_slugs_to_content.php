<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_categories', function (Blueprint $table) {
            $table->string('locale', 10)->default('tr')->after('id');
        });
        Schema::table('blog_categories', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });
        Schema::table('blog_categories', function (Blueprint $table) {
            $table->unique(['locale', 'slug']);
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('locale', 10)->default('tr')->after('id');
        });
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->unique(['locale', 'slug']);
        });

        Schema::table('doc_pages', function (Blueprint $table) {
            $table->string('locale', 10)->default('tr')->after('id');
        });
        Schema::table('doc_pages', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });
        Schema::table('doc_pages', function (Blueprint $table) {
            $table->unique(['locale', 'slug']);
        });

        Schema::table('site_pages', function (Blueprint $table) {
            $table->string('locale', 10)->default('tr')->after('id');
        });
        Schema::table('site_pages', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });
        Schema::table('site_pages', function (Blueprint $table) {
            $table->unique(['locale', 'slug']);
        });

        DB::table('site_pages')->where('slug', 'kurulum')->update(['slug' => 'setup']);
        DB::table('site_pages')->where('slug', 'fiyatlandirma')->update(['slug' => 'pricing']);

        DB::table('blog_categories')->where('slug', 'hosting-ve-gecis')->update(['slug' => 'hosting-migration']);
        DB::table('blog_categories')->where('slug', 'guvenlik')->update(['slug' => 'security']);
        DB::table('blog_categories')->where('slug', 'olceklendirme')->update(['slug' => 'scaling']);

        DB::table('blog_posts')->where('slug', 'shared-hostingten-kendi-panelime')->update(['slug' => 'from-shared-hosting']);
        DB::table('blog_posts')->where('slug', 'panel-guvenliginde-temel-hatalar')->update(['slug' => 'panel-security-basics']);
        DB::table('blog_posts')->where('slug', 'tek-sunucudan-coklu-clustera')->update(['slug' => 'single-server-to-cluster']);

        DB::table('doc_pages')->where('slug', 'sunucu-kurulumu')->update(['slug' => 'server-setup']);
        DB::table('doc_pages')->where('slug', 'hostvim-mimarisi')->update(['slug' => 'architecture']);
        DB::table('doc_pages')->where('slug', 'baslangic')->update(['slug' => 'getting-started']);
    }

    public function down(): void
    {
        DB::table('doc_pages')->where('slug', 'getting-started')->update(['slug' => 'baslangic']);
        DB::table('doc_pages')->where('slug', 'server-setup')->update(['slug' => 'sunucu-kurulumu']);
        DB::table('doc_pages')->where('slug', 'architecture')->update(['slug' => 'hostvim-mimarisi']);

        DB::table('blog_posts')->where('slug', 'from-shared-hosting')->update(['slug' => 'shared-hostingten-kendi-panelime']);
        DB::table('blog_posts')->where('slug', 'panel-security-basics')->update(['slug' => 'panel-guvenliginde-temel-hatalar']);
        DB::table('blog_posts')->where('slug', 'single-server-to-cluster')->update(['slug' => 'tek-sunucudan-coklu-clustera']);

        DB::table('blog_categories')->where('slug', 'hosting-migration')->update(['slug' => 'hosting-ve-gecis']);
        DB::table('blog_categories')->where('slug', 'security')->update(['slug' => 'guvenlik']);
        DB::table('blog_categories')->where('slug', 'scaling')->update(['slug' => 'olceklendirme']);

        DB::table('site_pages')->where('slug', 'setup')->update(['slug' => 'kurulum']);
        DB::table('site_pages')->where('slug', 'pricing')->update(['slug' => 'fiyatlandirma']);

        Schema::table('site_pages', function (Blueprint $table) {
            $table->dropUnique(['locale', 'slug']);
            $table->dropColumn('locale');
        });
        Schema::table('site_pages', function (Blueprint $table) {
            $table->unique('slug');
        });

        Schema::table('doc_pages', function (Blueprint $table) {
            $table->dropUnique(['locale', 'slug']);
            $table->dropColumn('locale');
        });
        Schema::table('doc_pages', function (Blueprint $table) {
            $table->unique('slug');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropUnique(['locale', 'slug']);
            $table->dropColumn('locale');
        });
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->unique('slug');
        });

        Schema::table('blog_categories', function (Blueprint $table) {
            $table->dropUnique(['locale', 'slug']);
            $table->dropColumn('locale');
        });
        Schema::table('blog_categories', function (Blueprint $table) {
            $table->unique('slug');
        });
    }
};
