<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->foreignId('blog_category_id')->nullable()->after('id')->constrained('blog_categories')->nullOnDelete();
            $table->string('meta_title')->nullable()->after('title');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('canonical_url', 2048)->nullable()->after('meta_description');
            $table->string('og_image', 2048)->nullable()->after('canonical_url');
            $table->string('robots', 64)->nullable()->after('og_image');
        });

        Schema::table('doc_pages', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('title');
            $table->text('meta_description')->nullable()->after('meta_title');
        });

        Schema::table('site_pages', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->dropColumn(['meta_title']);
        });

        Schema::table('doc_pages', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropForeign(['blog_category_id']);
            $table->dropColumn([
                'blog_category_id',
                'meta_title',
                'meta_description',
                'canonical_url',
                'og_image',
                'robots',
            ]);
        });

        Schema::dropIfExists('blog_categories');
    }
};
