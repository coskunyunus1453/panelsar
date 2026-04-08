<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('community_shadowbanned_at')->nullable()->after('community_admin_notes');
            $table->string('avatar_url', 512)->nullable()->after('community_shadowbanned_at');
        });

        Schema::table('community_site_meta', function (Blueprint $table) {
            $table->boolean('moderation_new_topics')->default(false)->after('enable_indexing');
            $table->boolean('moderation_new_posts')->default(false)->after('moderation_new_topics');
        });

        Schema::table('community_topics', function (Blueprint $table) {
            $table->string('moderation_status', 24)->default('approved')->after('status');
            $table->index(['status', 'moderation_status', 'last_activity_at'], 'community_topics_pub_mod_activity');
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->string('moderation_status', 24)->default('approved')->after('is_hidden');
            $table->index(['community_topic_id', 'is_hidden', 'moderation_status'], 'community_posts_topic_vis_mod');
        });

        Schema::create('community_tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('community_tag_topic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_topic_id')->constrained('community_topics')->cascadeOnDelete();
            $table->foreignId('community_tag_id')->constrained('community_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['community_topic_id', 'community_tag_id'], 'topic_tag_unique');
        });

        DB::table('community_topics')->update(['moderation_status' => 'approved']);
        DB::table('community_posts')->update(['moderation_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::dropIfExists('community_tag_topic');
        Schema::dropIfExists('community_tags');

        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropIndex('community_posts_topic_vis_mod');
            $table->dropColumn('moderation_status');
        });

        Schema::table('community_topics', function (Blueprint $table) {
            $table->dropIndex('community_topics_pub_mod_activity');
            $table->dropColumn('moderation_status');
        });

        Schema::table('community_site_meta', function (Blueprint $table) {
            $table->dropColumn(['moderation_new_topics', 'moderation_new_posts']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['community_shadowbanned_at', 'avatar_url']);
        });
    }
};
