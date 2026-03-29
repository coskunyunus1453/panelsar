<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->unique();
            $table->string('document_root');
            $table->string('php_version', 10)->default('8.2');
            $table->boolean('ssl_enabled')->default(false);
            $table->timestamp('ssl_expiry')->nullable();
            $table->string('status', 20)->default('pending');
            $table->boolean('is_primary')->default(false);
            $table->string('server_type', 20)->default('nginx');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
