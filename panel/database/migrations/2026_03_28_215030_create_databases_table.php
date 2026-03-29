<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->unique();
            $table->string('type', 20)->default('mysql');
            $table->string('username');
            $table->text('password');
            $table->string('host')->default('127.0.0.1');
            $table->integer('port')->default(3306);
            $table->bigInteger('size_mb')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('databases');
    }
};
