<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_modules', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('summary', 500)->nullable();
            $table->string('category', 50)->default('utility');
            $table->string('version', 40)->default('1.0.0');
            $table->boolean('is_paid')->default(false);
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 8)->default('USD');
            $table->boolean('is_public')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('user_plugin_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plugin_module_id')->constrained('plugin_modules')->cascadeOnDelete();
            $table->string('status', 30)->default('installed');
            $table->boolean('is_active')->default(false);
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'plugin_module_id']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_plugin_modules');
        Schema::dropIfExists('plugin_modules');
    }
};
