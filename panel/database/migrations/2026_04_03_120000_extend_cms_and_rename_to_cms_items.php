<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('cms_pages', 'cms_items');

        Schema::table('cms_items', function (Blueprint $table) {
            $table->text('excerpt')->nullable()->after('title');
            $table->boolean('featured')->default(false)->after('excerpt');
            $table->string('section', 128)->nullable()->after('featured');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_published')->default(false)->after('published_at');
        });

        DB::table('cms_items')->where('status', 'published')->update(['is_published' => true]);
        DB::table('cms_items')->where('status', '!=', 'published')->update(['is_published' => false]);
    }

    public function down(): void
    {
        Schema::table('cms_items', function (Blueprint $table) {
            $table->dropColumn([
                'excerpt',
                'featured',
                'section',
                'meta_title',
                'meta_description',
                'is_published',
            ]);
        });

        Schema::rename('cms_items', 'cms_pages');
    }
};
