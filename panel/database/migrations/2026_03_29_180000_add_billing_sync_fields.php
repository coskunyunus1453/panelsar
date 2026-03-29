<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('hosting_package_manual_override')->default(false);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('payment_provider', 32)->default('stripe');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('payment_provider');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('hosting_package_manual_override');
        });
    }
};
