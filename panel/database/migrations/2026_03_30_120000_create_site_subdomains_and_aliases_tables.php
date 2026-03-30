<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modern “Site” modeli: panelde her barındırma sitesi `domains` satırıdır (birincil FQDN).
     * Alt alan ve ek adlar bu çocuk tablolarda tutulur (addon/parked yok).
     */
    public function up(): void
    {
        Schema::create('site_subdomains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->string('hostname')->unique();
            $table->string('path_segment', 255);
            $table->string('document_root')->nullable();
            $table->timestamps();

            $table->index('domain_id');
        });

        Schema::create('site_domain_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->string('hostname')->unique();
            $table->timestamps();

            $table->index('domain_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_domain_aliases');
        Schema::dropIfExists('site_subdomains');
    }
};
