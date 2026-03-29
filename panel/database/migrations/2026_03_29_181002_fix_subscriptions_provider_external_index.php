<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 181001 normal index ekler; eski sürüm bu migration'da dropUnique kullanıyordu — indeks adı uyuşmayınca 1091.
     * Bilinen iki Laravel indeks adını DROP dene; yoksa hatayı yut (idempotent).
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ([
            'subscriptions_payment_provider_external_subscription_id_unique',
            'subscriptions_payment_provider_external_subscription_id_index',
        ] as $indexName) {
            try {
                DB::statement('ALTER TABLE `subscriptions` DROP INDEX `' . $indexName . '`');
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if (str_contains($msg, '1091')
                    || str_contains($msg, 'check that it exists')
                    || str_contains($msg, "Can't DROP")
                    || str_contains($msg, 'Unknown key')) {
                    continue;
                }
                throw $e;
            }
        }

        try {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index(['payment_provider', 'external_subscription_id']);
            });
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            // Duplicate key name (indeks zaten var)
            if (str_contains($msg, '1061') || str_contains($msg, 'Duplicate') || str_contains($msg, 'already exists')) {
                return;
            }
            throw $e;
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::statement('ALTER TABLE `subscriptions` DROP INDEX `subscriptions_payment_provider_external_subscription_id_index`');
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (! str_contains($msg, '1091') && ! str_contains($msg, 'check that it exists')) {
                throw $e;
            }
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unique(['payment_provider', 'external_subscription_id']);
        });
    }
};
