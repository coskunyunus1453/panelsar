<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('email')->unique();
            $table->text('password');
            $table->integer('quota_mb')->default(500);
            $table->integer('used_mb')->default(0);
            $table->string('status', 20)->default('active');
            $table->string('forwarding_address')->nullable();
            $table->boolean('autoresponder_enabled')->default(false);
            $table->text('autoresponder_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'domain_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_accounts');
    }
};
