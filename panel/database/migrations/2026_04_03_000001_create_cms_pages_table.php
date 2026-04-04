<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32);
            $table->string('slug', 255)->default('');
            $table->string('locale', 16);
            $table->string('title')->default('');
            $table->longText('body_markdown')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'slug', 'locale']);
            $table->index(['kind', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
