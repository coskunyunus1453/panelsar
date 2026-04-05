<?php

namespace Database\Seeders;

use App\Models\HostingPackage;
use App\Models\Role;
use App\Models\User;
use App\Models\VendorPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $vendorEnabled = (bool) config('hostvim.vendor_enabled', false);
        $roles = ['admin', 'reseller', 'user'];
        if ($vendorEnabled) {
            $roles = array_merge($roles, ['vendor_admin', 'vendor_support', 'vendor_finance', 'vendor_devops']);
        }

        foreach ($roles as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['is_system' => true]
            );
        }

        $this->call(PanelRolesAndPermissionsSeeder::class);

        if ($vendorEnabled) {
            // Vendor pricing model defaults (EUR, community free, both monthly/yearly).
            VendorPlan::query()->updateOrCreate(
                ['code' => 'community'],
                [
                    'name' => 'Community',
                    'billing_cycle' => 'yearly',
                    'price_minor' => 0,
                    'currency' => 'EUR',
                    'is_public' => true,
                    'limits' => [
                        'max_nodes' => 1,
                        'max_domains' => 3,
                        'support_tier' => 'community',
                    ],
                ]
            );
            VendorPlan::query()->updateOrCreate(
                ['code' => 'pro-monthly'],
                [
                    'name' => 'Pro Monthly',
                    'billing_cycle' => 'monthly',
                    'price_minor' => 2900,
                    'currency' => 'EUR',
                    'is_public' => true,
                    'limits' => [
                        'max_nodes' => 10,
                        'max_domains' => 200,
                        'support_tier' => 'standard',
                    ],
                ]
            );
            VendorPlan::query()->updateOrCreate(
                ['code' => 'pro-yearly'],
                [
                    'name' => 'Pro Yearly',
                    'billing_cycle' => 'yearly',
                    'price_minor' => 29900,
                    'currency' => 'EUR',
                    'is_public' => true,
                    'limits' => [
                        'max_nodes' => 10,
                        'max_domains' => 200,
                        'support_tier' => 'standard',
                    ],
                ]
            );
            VendorPlan::query()->updateOrCreate(
                ['code' => 'reseller-monthly'],
                [
                    'name' => 'Reseller Monthly',
                    'billing_cycle' => 'monthly',
                    'price_minor' => 7900,
                    'currency' => 'EUR',
                    'is_public' => true,
                    'limits' => [
                        'max_nodes' => 50,
                        'max_domains' => 2000,
                        'support_tier' => 'priority',
                    ],
                ]
            );
            VendorPlan::query()->updateOrCreate(
                ['code' => 'reseller-yearly'],
                [
                    'name' => 'Reseller Yearly',
                    'billing_cycle' => 'yearly',
                    'price_minor' => 79900,
                    'currency' => 'EUR',
                    'is_public' => true,
                    'limits' => [
                        'max_nodes' => 50,
                        'max_domains' => 2000,
                        'support_tier' => 'priority',
                    ],
                ]
            );
        }

        $starter = HostingPackage::firstOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter',
                'description' => 'Perfect for small websites',
                'disk_space_mb' => 5120,
                'bandwidth_mb' => 51200,
                'max_domains' => 1,
                'max_subdomains' => 3,
                'max_databases' => 2,
                'max_email_accounts' => 5,
                'max_ftp_accounts' => 2,
                'max_cron_jobs' => 3,
                'php_versions' => ['8.1', '8.2', '8.3'],
                'ssl_enabled' => true,
                'backup_enabled' => true,
                'price_monthly' => 4.99,
                'price_yearly' => 49.99,
                'currency' => 'USD',
                'sort_order' => 1,
            ]
        );

        HostingPackage::firstOrCreate(
            ['slug' => 'professional'],
            [
                'name' => 'Professional',
                'description' => 'For growing businesses',
                'disk_space_mb' => 25600,
                'bandwidth_mb' => 256000,
                'max_domains' => 10,
                'max_subdomains' => 25,
                'max_databases' => 10,
                'max_email_accounts' => 50,
                'max_ftp_accounts' => 10,
                'max_cron_jobs' => 10,
                'php_versions' => ['7.4', '8.0', '8.1', '8.2', '8.3'],
                'ssl_enabled' => true,
                'backup_enabled' => true,
                'price_monthly' => 14.99,
                'price_yearly' => 149.99,
                'currency' => 'USD',
                'sort_order' => 2,
            ]
        );

        HostingPackage::firstOrCreate(
            ['slug' => 'enterprise'],
            [
                'name' => 'Enterprise',
                'description' => 'Unlimited resources for large projects',
                'disk_space_mb' => -1,
                'bandwidth_mb' => -1,
                'max_domains' => -1,
                'max_subdomains' => -1,
                'max_databases' => -1,
                'max_email_accounts' => -1,
                'max_ftp_accounts' => -1,
                'max_cron_jobs' => -1,
                'php_versions' => ['7.4', '8.0', '8.1', '8.2', '8.3'],
                'ssl_enabled' => true,
                'backup_enabled' => true,
                'price_monthly' => 49.99,
                'price_yearly' => 499.99,
                'currency' => 'USD',
                'sort_order' => 3,
            ]
        );

        $adminEmail = env('HOSTVIM_ADMIN_EMAIL', env('PANELSAR_ADMIN_EMAIL', 'admin@hostvim.com'));
        $adminPasswordEnv = env('HOSTVIM_ADMIN_PASSWORD', env('PANELSAR_ADMIN_PASSWORD'));
        $adminPasswordForNew = ($adminPasswordEnv !== null && $adminPasswordEnv !== '')
            ? $adminPasswordEnv
            : 'password';

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Admin',
                'password' => Hash::make($adminPasswordForNew),
                'locale' => 'en',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        // Kurulum betiği HOSTVIM_ADMIN_PASSWORD verir; önceki yarım seed’de oluşmuş kullanıcıda şifre güncellenmezdi → 422.
        if ($adminPasswordEnv !== null && $adminPasswordEnv !== '') {
            $admin->password = Hash::make($adminPasswordEnv);
            $admin->save();
        }
        $admin->syncRoles(['admin']);

        $vendorAdminEmail = env('HOSTVIM_VENDOR_ADMIN_EMAIL', env('PANELSAR_VENDOR_ADMIN_EMAIL'));
        $vendorAdminPassword = env('HOSTVIM_VENDOR_ADMIN_PASSWORD', env('PANELSAR_VENDOR_ADMIN_PASSWORD'));
        if ($vendorEnabled && $vendorAdminEmail && $vendorAdminPassword) {
            $vendorAdmin = User::firstOrCreate(
                ['email' => $vendorAdminEmail],
                [
                    'name' => 'Vendor Admin',
                    'password' => Hash::make($vendorAdminPassword),
                    'locale' => 'en',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            $vendorAdmin->password = Hash::make($vendorAdminPassword);
            $vendorAdmin->save();
            $vendorAdmin->syncRoles(['vendor_admin']);
        }

        // Production default: only admin user.
        // Demo accounts are opt-in via HOSTVIM_SEED_DEMO_USERS=1 (veya eski PANELSAR_SEED_DEMO_USERS).
        $seedDemoUsers = filter_var((string) env('HOSTVIM_SEED_DEMO_USERS', env('PANELSAR_SEED_DEMO_USERS', false)), FILTER_VALIDATE_BOOLEAN);
        $this->call(CmsDeploymentDocSeeder::class);

        if ($seedDemoUsers) {
            $reseller = User::firstOrCreate(
                ['email' => 'reseller@hostvim.com'],
                [
                    'name' => 'Demo Reseller',
                    'password' => Hash::make('password'),
                    'locale' => 'en',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            $reseller->syncRoles(['reseller']);

            $user = User::firstOrCreate(
                ['email' => 'user@hostvim.com'],
                [
                    'name' => 'Demo User',
                    'password' => Hash::make('password'),
                    'locale' => 'tr',
                    'status' => 'active',
                    'hosting_package_id' => $starter->id,
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles(['user']);
        }
    }
}
