<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_site_meta', function (Blueprint $table): void {
            $table->string('site_title_en')->nullable()->after('site_title');
            $table->string('default_meta_title_en')->nullable()->after('default_meta_title');
            $table->text('default_meta_description_en')->nullable()->after('default_meta_description');
        });

        Schema::table('community_categories', function (Blueprint $table): void {
            $table->string('name_en')->nullable()->after('name');
            $table->text('description_en')->nullable()->after('description');
            $table->string('meta_title_en')->nullable()->after('meta_title');
            $table->text('meta_description_en')->nullable()->after('meta_description');
        });
    }

    public function down(): void
    {
        Schema::table('community_site_meta', function (Blueprint $table): void {
            $table->dropColumn([
                'site_title_en',
                'default_meta_title_en',
                'default_meta_description_en',
            ]);
        });

        Schema::table('community_categories', function (Blueprint $table): void {
            $table->dropColumn([
                'name_en',
                'description_en',
                'meta_title_en',
                'meta_description_en',
            ]);
        });
    }
};
