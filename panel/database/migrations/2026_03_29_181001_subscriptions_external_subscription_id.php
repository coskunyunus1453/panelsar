<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('external_subscription_id')->nullable();
            $table->index(['payment_provider', 'external_subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['payment_provider', 'external_subscription_id']);
            $table->dropColumn('external_subscription_id');
        });
    }
};
