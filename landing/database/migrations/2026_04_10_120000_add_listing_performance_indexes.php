<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->index(['locale', 'is_published', 'published_at'], 'blog_posts_locale_pub_date');
        });

        Schema::table('site_pages', function (Blueprint $table) {
            $table->index(['locale', 'is_published', 'sort_order'], 'site_pages_locale_pub_sort');
        });

        Schema::table('doc_pages', function (Blueprint $table) {
            $table->index(['locale', 'is_published', 'sort_order'], 'doc_pages_locale_pub_sort');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->index(['is_active', 'sort_order'], 'plans_active_sort');
        });

        Schema::table('saas_licenses', function (Blueprint $table) {
            $table->index(['status', 'expires_at'], 'saas_licenses_status_expires');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropIndex('blog_posts_locale_pub_date');
        });

        Schema::table('site_pages', function (Blueprint $table) {
            $table->dropIndex('site_pages_locale_pub_sort');
        });

        Schema::table('doc_pages', function (Blueprint $table) {
            $table->dropIndex('doc_pages_locale_pub_sort');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropIndex('plans_active_sort');
        });

        Schema::table('saas_licenses', function (Blueprint $table) {
            $table->dropIndex('saas_licenses_status_expires');
        });
    }
};
