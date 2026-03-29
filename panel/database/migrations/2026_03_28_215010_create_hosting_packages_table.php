<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosting_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('disk_space_mb')->default(-1);
            $table->integer('bandwidth_mb')->default(-1);
            $table->integer('max_domains')->default(1);
            $table->integer('max_subdomains')->default(5);
            $table->integer('max_databases')->default(1);
            $table->integer('max_email_accounts')->default(5);
            $table->integer('max_ftp_accounts')->default(1);
            $table->integer('max_cron_jobs')->default(3);
            $table->integer('cpu_limit')->nullable();
            $table->integer('memory_limit_mb')->nullable();
            $table->json('php_versions')->nullable();
            $table->boolean('ssl_enabled')->default(true);
            $table->boolean('backup_enabled')->default(true);
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('reseller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('hosting_package_id')->references('id')->on('hosting_packages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['hosting_package_id']);
        });
        Schema::dropIfExists('hosting_packages');
    }
};
