<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['payment_provider', 'external_subscription_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['payment_provider', 'external_subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['payment_provider', 'external_subscription_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unique(['payment_provider', 'external_subscription_id']);
        });
    }
};
