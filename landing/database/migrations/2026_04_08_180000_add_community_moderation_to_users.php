<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('community_banned_at')->nullable()->after('remember_token');
            $table->string('community_ban_reason', 500)->nullable()->after('community_banned_at');
            $table->text('community_admin_notes')->nullable()->after('community_ban_reason');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['community_banned_at', 'community_ban_reason', 'community_admin_notes']);
        });
    }
};
