<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_forwarders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('destination');
            $table->boolean('keep_copy')->default(false);
            $table->timestamps();

            $table->index(['domain_id']);
            $table->unique(['domain_id', 'source', 'destination']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_forwarders');
    }
};
