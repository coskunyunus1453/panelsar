<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('company')->nullable();
            $table->string('phone')->nullable();
            $table->string('status', 32)->default('active'); // active, suspended
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('saas_license_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('default_limits')->nullable();
            /** @var array<string, bool> modül anahtarı => varsayılan açık */
            $table->json('default_modules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('saas_product_modules', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label');
            $table->string('description')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('saas_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_key', 80)->unique();
            $table->foreignId('saas_customer_id')->constrained('saas_customers')->cascadeOnDelete();
            $table->foreignId('saas_license_product_id')->constrained('saas_license_products')->restrictOnDelete();
            $table->string('status', 32)->default('active'); // active, suspended, expired, revoked
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('limits_override')->nullable();
            /** @var array<string, bool>|null lisans bazlı modül ezer */
            $table->json('modules_override')->nullable();
            $table->string('subscription_status', 32)->nullable(); // active, past_due, canceled, none
            $table->timestamp('subscription_renews_at')->nullable();
            $table->string('billing_provider')->nullable();
            $table->string('billing_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_licenses');
        Schema::dropIfExists('saas_product_modules');
        Schema::dropIfExists('saas_license_products');
        Schema::dropIfExists('saas_customers');
    }
};
