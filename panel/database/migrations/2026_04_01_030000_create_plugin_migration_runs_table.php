<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_migration_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plugin_module_id')->constrained('plugin_modules')->cascadeOnDelete();
            $table->string('source_type', 30);
            $table->string('source_host', 255);
            $table->unsignedSmallInteger('source_port')->default(22);
            $table->string('source_user', 120)->nullable();
            $table->string('status', 20)->default('queued');
            $table->boolean('dry_run')->default(true);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->json('options')->nullable();
            $table->longText('output')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_migration_runs');
    }
};
