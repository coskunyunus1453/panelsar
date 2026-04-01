<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->string('status', 24)->default('active')->index(); // active, suspended, archived
            $table->string('contact_email', 191)->nullable();
            $table->string('country', 8)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique(); // community, pro, reseller
            $table->string('name', 120);
            $table->string('billing_cycle', 16)->default('monthly'); // monthly, yearly
            $table->unsignedInteger('price_minor')->default(0); // cents/kurus
            $table->string('currency', 8)->default('USD');
            $table->boolean('is_public')->default(true);
            $table->json('limits')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_features', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique(); // e.g. deploy.rollback
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('kind', 16)->default('boolean'); // boolean, quota
            $table->timestamps();
        });

        Schema::create('vendor_plan_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('vendor_plans')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('vendor_features')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('quota')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'feature_id']);
        });

        Schema::create('vendor_licenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('vendor_tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('vendor_plans')->cascadeOnDelete();
            $table->string('license_key', 96)->unique();
            $table->string('status', 24)->default('active')->index(); // active, expired, suspended, revoked
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_verified_at')->nullable();
            $table->json('constraints')->nullable(); // max_nodes, ip_bindings...
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('license_id')->constrained('vendor_licenses')->cascadeOnDelete();
            $table->string('instance_id', 96)->index();
            $table->string('fingerprint', 191)->nullable()->index();
            $table->string('hostname', 191)->nullable();
            $table->string('public_ip', 64)->nullable();
            $table->string('agent_version', 48)->nullable();
            $table->string('status', 24)->default('online')->index(); // online, offline, blocked
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->json('capabilities')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['license_id', 'instance_id']);
        });

        Schema::create('vendor_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('vendor_tenants')->nullOnDelete();
            $table->foreignId('license_id')->nullable()->constrained('vendor_licenses')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 120)->index();
            $table->string('severity', 16)->default('info')->index(); // info, warning, critical
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_audit_events');
        Schema::dropIfExists('vendor_nodes');
        Schema::dropIfExists('vendor_licenses');
        Schema::dropIfExists('vendor_plan_features');
        Schema::dropIfExists('vendor_features');
        Schema::dropIfExists('vendor_plans');
        Schema::dropIfExists('vendor_tenants');
    }
};

