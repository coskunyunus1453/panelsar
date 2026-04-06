<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saas_license_products', function (Blueprint $table) {
            /** PayTR: TL kuruş (örn. 99,99 TL → 9999) */
            $table->unsignedInteger('price_try_minor')->nullable()->after('sort_order');
            /** Stripe: USD cent (örn. 99,99 USD → 9999) */
            $table->unsignedInteger('price_usd_minor')->nullable()->after('price_try_minor');
        });

        Schema::create('saas_checkout_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_ref', 64)->unique();
            $table->string('provider', 16); // paytr | stripe
            $table->string('locale', 10)->default('en');
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->foreignId('saas_license_product_id')->constrained('saas_license_products')->restrictOnDelete();
            $table->unsignedInteger('amount_minor');
            $table->string('currency', 8);
            $table->string('status', 24)->default('pending'); // pending, completed, failed
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->foreignId('saas_license_id')->nullable()->constrained('saas_licenses')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->text('failure_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_checkout_orders');

        Schema::table('saas_license_products', function (Blueprint $table) {
            $table->dropColumn(['price_try_minor', 'price_usd_minor']);
        });
    }
};
