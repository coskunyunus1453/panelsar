<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('repo_url')->nullable();
            $table->string('branch', 100)->default('main');
            $table->string('runtime', 20)->default('laravel');
            $table->string('webhook_token', 120)->nullable();
            $table->boolean('auto_deploy')->default(false);
            $table->timestamps();
        });

        Schema::create('deployment_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trigger', 20)->default('manual');
            $table->string('status', 20)->default('running');
            $table->string('commit_hash', 64)->nullable();
            $table->longText('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['domain_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_runs');
        Schema::dropIfExists('deployment_configs');
    }
};
