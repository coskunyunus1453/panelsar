<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('schedule');
            $table->text('command');
            $table->string('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->string('engine_job_id')->nullable();
            $table->timestamps();
        });

        Schema::create('ftp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username')->unique();
            $table->text('password');
            $table->string('home_directory');
            $table->integer('quota_mb')->default(-1);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('name');
            $table->text('value');
            $table->integer('ttl')->default(3600);
            $table->integer('priority')->nullable();
            $table->timestamps();
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('full');
            $table->string('file_path')->nullable();
            $table->bigInteger('size_mb')->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hosting_package_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('billing_cycle', 20)->default('monthly');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('backups');
        Schema::dropIfExists('dns_records');
        Schema::dropIfExists('ftp_accounts');
        Schema::dropIfExists('cron_jobs');
    }
};
