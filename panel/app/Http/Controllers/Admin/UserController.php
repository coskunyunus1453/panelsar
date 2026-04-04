<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\SafeAuditLogger;
use App\Services\UserHostingPackageSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(
        private UserHostingPackageSync $hostingPackageSync,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $users = User::with(['roles', 'hostingPackage'])
            ->when($request->user()->isReseller() && ! $request->user()->isAdmin(), function ($q) use ($request) {
                $q->where('parent_id', $request->user()->id);
            })
            ->when($request->search, function ($q, $s) {
                $q->where(function ($q2) use ($s) {
                    $q2->where('name', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%");
                });
            })
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->role, fn ($q, $r) => $q->role($r))
            ->latest()
            ->paginate(20);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $rolesTable = config('permission.table_names.roles');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', 'string', Rule::exists($rolesTable, 'name')->where('guard_name', 'web')],
            'hosting_package_id' => 'nullable|exists:hosting_packages,id',
            'locale' => 'nullable|string|in:en,tr,de,fr,es,pt,zh,ja,ar,ru',
        ]);

        $roleModel = Role::query()->where('name', $validated['role'])->where('guard_name', 'web')->firstOrFail();

        if ($request->user()->isReseller() && ! $request->user()->isAdmin()) {
            abort_unless($this->resellerMayAssignRole($request->user(), $roleModel), 403, __('users.reseller_role_forbidden'));
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'locale' => $validated['locale'] ?? 'en',
            'status' => 'active',
            'hosting_package_id' => $validated['hosting_package_id'] ?? null,
            'hosting_package_manual_override' => array_key_exists('hosting_package_id', $validated),
            'parent_id' => ($request->user()->isReseller() && ! $request->user()->isAdmin())
                ? $request->user()->id
                : null,
        ]);

        $user->syncRoles([$roleModel->name]);

        return response()->json([
            'message' => __('users.created'),
            'user' => $user->load('roles'),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => $user->load(['roles', 'hostingPackage', 'domains', 'subscriptions']),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $rolesTable = config('permission.table_names.roles');
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$user->id,
            'status' => 'sometimes|string|in:active,suspended,pending,disabled',
            'hosting_package_id' => 'nullable|exists:hosting_packages,id',
            'locale' => 'nullable|string',
            'sync_hosting_package_from_billing' => 'sometimes|boolean',
            'role' => ['sometimes', 'string', Rule::exists($rolesTable, 'name')->where('guard_name', 'web')],
        ]);

        $roleModel = null;
        if (isset($validated['role'])) {
            $roleModel = Role::query()->where('name', $validated['role'])->where('guard_name', 'web')->firstOrFail();
            if ($request->user()->isReseller() && ! $request->user()->isAdmin()) {
                abort_unless($this->resellerMayAssignRole($request->user(), $roleModel), 403, __('users.reseller_role_forbidden'));
            }
        }
        unset($validated['role']);

        $syncFromBilling = (bool) ($validated['sync_hosting_package_from_billing'] ?? false);
        unset($validated['sync_hosting_package_from_billing']);

        if ($syncFromBilling) {
            unset($validated['hosting_package_id']);
            $user->fill($validated);
            $user->hosting_package_manual_override = false;
            $user->save();
            $this->hostingPackageSync->syncFromSubscriptions($user->id);
        } else {
            if (array_key_exists('hosting_package_id', $validated)) {
                $validated['hosting_package_manual_override'] = true;
            }
            if ($validated !== []) {
                $user->update($validated);
            }
        }

        if ($roleModel !== null) {
            $user->syncRoles([$roleModel->name]);
        }

        return response()->json([
            'message' => __('users.updated'),
            'user' => $user->fresh()->load('roles'),
        ]);
    }

    public function suspend(User $user): JsonResponse
    {
        $user->update(['status' => 'suspended']);

        return response()->json(['message' => __('users.suspended')]);
    }

    public function activate(User $user): JsonResponse
    {
        $user->update(['status' => 'active']);

        return response()->json(['message' => __('users.activated')]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $user->forceFill([
            'password' => Hash::make((string) $validated['password']),
        ])->save();

        SafeAuditLogger::info('hostvim.user_password_reset', [
            'target_user_id' => $user->id,
            'target_email_hash' => hash('sha256', strtolower(trim((string) $user->email))),
        ], $request);

        return response()->json([
            'message' => 'Kullanıcı şifresi güvenli şekilde güncellendi.',
        ]);
    }

    private function resellerMayAssignRole(User $reseller, Role $role): bool
    {
        if ($role->name === 'admin' || $role->name === 'reseller') {
            return false;
        }

        if ($role->name === 'user') {
            return true;
        }

        if (! $role->assignable_by_reseller) {
            return false;
        }

        if ($role->owner_user_id !== null && (int) $role->owner_user_id !== (int) $reseller->id) {
            return false;
        }

        return true;
    }
}
