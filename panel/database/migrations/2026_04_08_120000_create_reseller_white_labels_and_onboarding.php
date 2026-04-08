<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_white_labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug', 64)->nullable()->unique();
            $table->string('hostname', 255)->nullable()->unique();
            $table->string('primary_color', 7)->nullable();
            $table->string('secondary_color', 7)->nullable();
            $table->string('logo_customer_basename', 191)->nullable();
            $table->string('logo_admin_basename', 191)->nullable();
            $table->string('login_title', 200)->nullable();
            $table->string('login_subtitle', 500)->nullable();
            $table->text('mail_footer_plain')->nullable();
            $table->text('onboarding_html')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('onboarding_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('onboarding_completed_at');
        });
        Schema::dropIfExists('reseller_white_labels');
    }
};
