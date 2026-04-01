<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('vendor_tenants')->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained('vendor_licenses')->nullOnDelete();
            $table->string('provider', 32)->default('manual')->index();
            $table->string('external_id', 191)->nullable()->index();
            $table->string('status', 32)->default('active')->index(); // active, trialing, past_due, canceled, unpaid
            $table->unsignedInteger('amount_minor')->default(0);
            $table->string('currency', 8)->default('USD');
            $table->string('billing_cycle', 16)->default('monthly');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
        });

        Schema::create('vendor_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('vendor_tenants')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('vendor_subscriptions')->nullOnDelete();
            $table->string('provider', 32)->default('manual')->index();
            $table->string('external_id', 191)->nullable()->index();
            $table->string('status', 32)->default('open')->index(); // open, paid, failed, void
            $table->unsignedInteger('amount_minor')->default(0);
            $table->string('currency', 8)->default('USD');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
        });

        Schema::create('vendor_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('vendor_tenants')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('vendor_invoices')->nullOnDelete();
            $table->string('provider', 32)->default('manual')->index();
            $table->string('external_id', 191)->nullable()->index();
            $table->string('status', 32)->default('succeeded')->index(); // succeeded, failed, pending
            $table->unsignedInteger('amount_minor')->default(0);
            $table->string('currency', 8)->default('USD');
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
        });

        Schema::create('vendor_support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('vendor_tenants')->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained('vendor_licenses')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 191);
            $table->string('status', 24)->default('open')->index(); // open, in_progress, waiting_customer, closed
            $table->string('priority', 16)->default('normal')->index(); // low, normal, high, critical
            $table->text('last_message')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('vendor_support_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('vendor_support_tickets')->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_type', 24)->default('vendor')->index(); // vendor, customer, system
            $table->text('message');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_support_messages');
        Schema::dropIfExists('vendor_support_tickets');
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('vendor_invoices');
        Schema::dropIfExists('vendor_subscriptions');
    }
};

