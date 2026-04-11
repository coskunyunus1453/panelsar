<?php

namespace Database\Seeders;

use App\Models\SaasLicenseProduct;
use App\Models\SaasProductModule;
use Illuminate\Database\Seeder;

class SaasBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['key' => 'vendor_panel', 'label' => 'Vendor kontrol düzlemi', 'is_paid' => true, 'sort_order' => 10],
            ['key' => 'backups_pro', 'label' => 'Gelişmiş yedekleme', 'is_paid' => true, 'sort_order' => 20],
            ['key' => 'monitoring_advanced', 'label' => 'Gelişmiş izleme', 'is_paid' => true, 'sort_order' => 30],
            ['key' => 'ai_advisor', 'label' => 'AI danışman', 'is_paid' => true, 'sort_order' => 40],
            ['key' => 'stripe_billing', 'label' => 'Stripe faturalama entegrasyonu', 'is_paid' => true, 'sort_order' => 50],
        ];
        foreach ($modules as $m) {
            SaasProductModule::query()->updateOrCreate(
                ['key' => $m['key']],
                array_merge($m, ['is_active' => true, 'description' => null])
            );
        }

        SaasLicenseProduct::query()->updateOrCreate(
            ['code' => 'community'],
            [
                'name' => 'Hostvim Community',
                'description' => 'Freemium barındırma paneli',
                'default_limits' => ['max_sites' => 5],
                'default_modules' => [
                    'vendor_panel' => false,
                    'backups_pro' => false,
                    'monitoring_advanced' => false,
                    'ai_advisor' => false,
                    'stripe_billing' => false,
                ],
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        SaasLicenseProduct::query()->updateOrCreate(
            ['code' => 'pro'],
            [
                'name' => 'Hostvim Pro',
                'description' => 'Tam özellik + vendor',
                'default_limits' => ['max_sites' => 500],
                'default_modules' => [
                    'vendor_panel' => true,
                    'backups_pro' => true,
                    'monitoring_advanced' => true,
                    'ai_advisor' => true,
                    'stripe_billing' => true,
                ],
                'is_active' => true,
                'sort_order' => 10,
                /** Örnek perakende: admin’den güncelleyin; minor birim (TL kuruş / cent) */
                'price_try_minor' => 199900,
                'price_usd_minor' => 19900,
                'price_eur_minor' => 18500,
            ]
        );
    }
}
