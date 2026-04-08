<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PanelRolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $vendorEnabled = (bool) config('hostvim.vendor_enabled', false);
        $registry = config('hostvim_abilities', []);
        if (! is_array($registry)) {
            return;
        }

        foreach ($registry as $row) {
            if (! is_array($row) || empty($row['name'])) {
                continue;
            }
            Permission::query()->firstOrCreate(
                ['name' => (string) $row['name'], 'guard_name' => 'web'],
                []
            );
        }

        $allNames = collect($registry)->pluck('name')->filter()->values()->all();
        $userDeny = [
            'monitoring:server',
            'security:write',
            'webserver:read',
            'webserver:write',
            'php:read',
            'php:write',
            'reseller:users',
            'reseller:packages',
            'reseller:roles',
            'reseller:white_label',
        ];
        $userAllow = array_values(array_diff($allNames, $userDeny));
        $resellerDeny = [
            'monitoring:server',
            'security:write',
            'webserver:read',
            'webserver:write',
            'php:read',
            'php:write',
        ];
        $resellerAllow = array_values(array_diff($allNames, $resellerDeny));

        $vendorAbilityNames = collect($allNames)
            ->filter(static fn (string $n) => str_starts_with($n, 'vendor:'))
            ->values()
            ->all();

        /** Vendor yönetici: bayi (reseller) kapsamı + tüm vendor:* — müşteri yetkileri buna dahil */
        $vendorAdminAllow = array_values(array_unique(array_merge($resellerAllow, $vendorAbilityNames)));

        /** Müşteri hosting + vendor okuma / destek / denetim */
        $vendorSupportAllow = array_values(array_unique(array_merge($userAllow, [
            'vendor:read',
            'vendor:support',
            'vendor:audit',
        ])));

        /** Müşteri hosting + lisans faturalama uçları + denetim */
        $vendorFinanceAllow = array_values(array_unique(array_merge($userAllow, [
            'vendor:read',
            'vendor:billing',
            'vendor:audit',
        ])));

        /** Müşteri hosting + tenant/lisans/plan yönetimi, düğümler, denetim (fatura/destek uçları hariç) */
        $vendorDevopsAllow = array_values(array_unique(array_merge($userAllow, [
            'vendor:read',
            'vendor:write',
            'vendor:nodes',
            'vendor:audit',
        ])));

        $admin = Role::query()->where('name', 'admin')->where('guard_name', 'web')->first();
        $vendorAdmin = Role::query()->where('name', 'vendor_admin')->where('guard_name', 'web')->first();
        $vendorSupport = Role::query()->where('name', 'vendor_support')->where('guard_name', 'web')->first();
        $vendorFinance = Role::query()->where('name', 'vendor_finance')->where('guard_name', 'web')->first();
        $vendorDevops = Role::query()->where('name', 'vendor_devops')->where('guard_name', 'web')->first();
        $reseller = Role::query()->where('name', 'reseller')->where('guard_name', 'web')->first();
        $user = Role::query()->where('name', 'user')->where('guard_name', 'web')->first();

        if ($admin) {
            $admin->syncPermissions(Permission::query()->where('guard_name', 'web')->get());
            $admin->update([
                'is_system' => true,
                'assignable_by_reseller' => false,
                'display_name' => 'Yönetici',
            ]);
        }
        if ($vendorEnabled && $vendorAdmin) {
            $vendorAdmin->syncPermissions(Permission::query()->whereIn('name', $vendorAdminAllow)->where('guard_name', 'web')->get());
            $vendorAdmin->update([
                'is_system' => true,
                'assignable_by_reseller' => false,
                'display_name' => 'Vendor Yonetici',
            ]);
        }
        if ($vendorEnabled && $vendorSupport) {
            $vendorSupport->syncPermissions(Permission::query()->whereIn('name', $vendorSupportAllow)->where('guard_name', 'web')->get());
            $vendorSupport->update(['is_system' => true, 'assignable_by_reseller' => false, 'display_name' => 'Vendor Support']);
        }
        if ($vendorEnabled && $vendorFinance) {
            $vendorFinance->syncPermissions(Permission::query()->whereIn('name', $vendorFinanceAllow)->where('guard_name', 'web')->get());
            $vendorFinance->update(['is_system' => true, 'assignable_by_reseller' => false, 'display_name' => 'Vendor Finance']);
        }
        if ($vendorEnabled && $vendorDevops) {
            $vendorDevops->syncPermissions(Permission::query()->whereIn('name', $vendorDevopsAllow)->where('guard_name', 'web')->get());
            $vendorDevops->update(['is_system' => true, 'assignable_by_reseller' => false, 'display_name' => 'Vendor DevOps']);
        }
        if ($reseller) {
            $reseller->syncPermissions(Permission::query()->whereIn('name', $resellerAllow)->where('guard_name', 'web')->get());
            $reseller->update([
                'is_system' => true,
                'assignable_by_reseller' => false,
                'display_name' => 'Bayi',
            ]);
        }
        if ($user) {
            $user->syncPermissions(Permission::query()->whereIn('name', $userAllow)->where('guard_name', 'web')->get());
            $user->update([
                'is_system' => true,
                'assignable_by_reseller' => true,
                'display_name' => 'Müşteri',
            ]);
        }
    }
}
