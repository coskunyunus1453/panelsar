<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('level', 16)->default('info');
            $table->string('title', 160);
            $table->text('message')->nullable();
            $table->string('path', 255)->nullable();
            $table->string('dedupe_key', 191)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
    }
};

