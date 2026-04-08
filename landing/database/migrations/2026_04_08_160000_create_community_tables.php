<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_site_meta', function (Blueprint $table) {
            $table->id();
            $table->string('site_title')->default('Community');
            $table->string('default_meta_title')->nullable();
            $table->text('default_meta_description')->nullable();
            $table->string('og_image_url')->nullable();
            $table->string('twitter_site')->nullable();
            $table->boolean('enable_indexing')->default(true);
            $table->timestamps();
        });

        Schema::create('community_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('robots_override')->nullable();
            $table->timestamps();
        });

        Schema::create('community_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_category_id')->constrained('community_categories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');
            $table->string('excerpt', 600)->nullable();
            $table->string('status', 32)->default('published');
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_solved')->default(false);
            $table->unsignedBigInteger('best_answer_post_id')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('robots_override')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['community_category_id', 'status']);
            $table->index('last_activity_at');
        });

        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_topic_id')->constrained('community_topics')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->longText('body');
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->index(['community_topic_id', 'is_hidden']);
        });

        Schema::table('community_topics', function (Blueprint $table) {
            $table->foreign('best_answer_post_id')
                ->references('id')
                ->on('community_posts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('community_topics', function (Blueprint $table) {
            $table->dropForeign(['best_answer_post_id']);
        });
        Schema::dropIfExists('community_posts');
        Schema::dropIfExists('community_topics');
        Schema::dropIfExists('community_categories');
        Schema::dropIfExists('community_site_meta');
    }
};
