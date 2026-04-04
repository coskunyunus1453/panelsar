<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ResellerRoleController extends Controller
{
    /** Bayinin atayabileceği izin adlarına göre süzülmüş yetenek listesi (UI için). */
    public function abilityRegistry(Request $request): JsonResponse
    {
        $allowed = $request->user()->getAllPermissions()->pluck('name')->all();
        $registry = config('hostvim_abilities', []);
        $filtered = array_values(array_filter(
            is_array($registry) ? $registry : [],
            static fn ($row) => is_array($row) && ! empty($row['name']) && in_array((string) $row['name'], $allowed, true)
        ));

        return response()->json(['abilities' => $filtered]);
    }

    public function index(Request $request): JsonResponse
    {
        $uid = $request->user()->id;
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->where(function ($q) use ($uid) {
                $q->where('name', 'user')
                    ->orWhere('owner_user_id', $uid)
                    ->orWhere(function ($q2) {
                        $q2->where('assignable_by_reseller', true)->whereNull('owner_user_id');
                    });
            })
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get();

        return response()->json($roles);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $permTable = config('permission.table_names.permissions');
        $validated = $request->validate([
            'display_name' => 'required|string|max:120',
            'permissions' => 'required|array|min:1',
            'permissions.*' => ['string', Rule::exists($permTable, 'name')->where('guard_name', 'web')],
        ]);

        $allowed = $user->getAllPermissions()->pluck('name')->all();
        foreach ($validated['permissions'] as $p) {
            if (! in_array($p, $allowed, true)) {
                abort(422, __('users.reseller_permission_oob'));
            }
        }

        $base = 'r'.$user->id.'_'.Str::slug($validated['display_name'], '_');
        $base = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $base)) ?: 'role';
        $name = $base;
        $i = 0;
        while (Role::query()->where('name', $name)->where('guard_name', 'web')->exists()) {
            $name = $base.'_'.(++$i);
        }

        $role = Role::create([
            'name' => $name,
            'guard_name' => 'web',
            'display_name' => $validated['display_name'],
            'owner_user_id' => $user->id,
            'assignable_by_reseller' => true,
            'is_system' => false,
        ]);
        $role->syncPermissions($validated['permissions']);

        return response()->json($role->load('permissions'), 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_unless($role->guard_name === 'web', 404);
        abort_if($role->is_system, 403);
        abort_unless((int) $role->owner_user_id === (int) $request->user()->id, 403);

        $permTable = config('permission.table_names.permissions');
        $validated = $request->validate([
            'display_name' => 'nullable|string|max:120',
            'permissions' => 'required|array|min:1',
            'permissions.*' => ['string', Rule::exists($permTable, 'name')->where('guard_name', 'web')],
        ]);

        $allowed = $request->user()->getAllPermissions()->pluck('name')->all();
        foreach ($validated['permissions'] as $p) {
            if (! in_array($p, $allowed, true)) {
                abort(422, __('users.reseller_permission_oob'));
            }
        }

        if (! empty($validated['display_name'])) {
            $role->display_name = $validated['display_name'];
            $role->save();
        }
        $role->syncPermissions($validated['permissions']);

        return response()->json($role->fresh()->load('permissions'));
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        abort_unless($role->guard_name === 'web', 404);
        abort_if($role->is_system, 403);
        abort_unless((int) $role->owner_user_id === (int) $request->user()->id, 403);

        $role->delete();

        return response()->json(['message' => 'OK']);
    }
}
