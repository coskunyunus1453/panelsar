<?php

namespace Database\Seeders;

use App\Models\HostingPackage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'reseller', 'user'] as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web']
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

        $adminEmail = env('PANELSAR_ADMIN_EMAIL', 'admin@panelsar.com');
        $adminPassword = env('PANELSAR_ADMIN_PASSWORD', 'password');

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Admin',
                'password' => Hash::make($adminPassword),
                'locale' => 'en',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['admin']);

        // Production default: only admin user.
        // Demo accounts are opt-in via PANELSAR_SEED_DEMO_USERS=1.
        $seedDemoUsers = filter_var((string) env('PANELSAR_SEED_DEMO_USERS', false), FILTER_VALIDATE_BOOLEAN);
        if ($seedDemoUsers) {
            $reseller = User::firstOrCreate(
                ['email' => 'reseller@panelsar.com'],
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
                ['email' => 'user@panelsar.com'],
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
