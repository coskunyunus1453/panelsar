<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PanelRolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registry = config('panelsar_abilities', []);
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

        $admin = Role::query()->where('name', 'admin')->where('guard_name', 'web')->first();
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
