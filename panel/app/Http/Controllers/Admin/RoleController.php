<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function registry(): JsonResponse
    {
        return response()->json(['abilities' => config('panelsar_abilities')]);
    }

    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions:id,name')
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        return response()->json($roles);
    }

    public function store(Request $request): JsonResponse
    {
        $permTable = config('permission.table_names.permissions');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9_\-]*$/', Rule::unique(config('permission.table_names.roles'), 'name')->where('guard_name', 'web')],
            'display_name' => 'nullable|string|max:120',
            'assignable_by_reseller' => 'sometimes|boolean',
            'permissions' => 'required|array',
            'permissions.*' => ['string', Rule::exists($permTable, 'name')->where('guard_name', 'web')],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'display_name' => $validated['display_name'] ?? $validated['name'],
            'assignable_by_reseller' => $validated['assignable_by_reseller'] ?? false,
            'is_system' => false,
            'owner_user_id' => null,
        ]);
        $role->syncPermissions($validated['permissions']);

        return response()->json($role->load('permissions'), 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_unless($role->guard_name === 'web', 404);
        abort_if($role->is_system, 403);

        $permTable = config('permission.table_names.permissions');
        $validated = $request->validate([
            'display_name' => 'nullable|string|max:120',
            'assignable_by_reseller' => 'sometimes|boolean',
            'permissions' => 'sometimes|array',
            'permissions.*' => ['string', Rule::exists($permTable, 'name')->where('guard_name', 'web')],
        ]);

        if (array_key_exists('display_name', $validated)) {
            $role->display_name = $validated['display_name'];
        }
        if (array_key_exists('assignable_by_reseller', $validated)) {
            $role->assignable_by_reseller = (bool) $validated['assignable_by_reseller'];
        }
        $role->save();

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json($role->fresh()->load('permissions'));
    }

    public function destroy(Role $role): JsonResponse
    {
        abort_unless($role->guard_name === 'web', 404);
        abort_if($role->is_system, 403);
        $role->delete();

        return response()->json(['message' => 'OK']);
    }
}
