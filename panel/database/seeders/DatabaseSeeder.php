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
        // Roles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'reseller']);
        Role::create(['name' => 'user']);

        // Default Hosting Packages
        $starter = HostingPackage::create([
            'name' => 'Starter',
            'slug' => 'starter',
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
        ]);

        HostingPackage::create([
            'name' => 'Professional',
            'slug' => 'professional',
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
        ]);

        HostingPackage::create([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
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
        ]);

        // Admin User
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@panelsar.com',
            'password' => Hash::make('password'),
            'locale' => 'en',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        // Demo Reseller
        $reseller = User::create([
            'name' => 'Demo Reseller',
            'email' => 'reseller@panelsar.com',
            'password' => Hash::make('password'),
            'locale' => 'en',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $reseller->assignRole('reseller');

        // Demo User
        $user = User::create([
            'name' => 'Demo User',
            'email' => 'user@panelsar.com',
            'password' => Hash::make('password'),
            'locale' => 'tr',
            'status' => 'active',
            'hosting_package_id' => $starter->id,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('user');
    }
}
