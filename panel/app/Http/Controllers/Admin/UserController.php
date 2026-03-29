<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserHostingPackageSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:admin,reseller,user',
            'hosting_package_id' => 'nullable|exists:hosting_packages,id',
            'locale' => 'nullable|string|in:en,tr,de,fr,es,pt,zh,ja,ar,ru',
        ]);

        if ($request->user()->isReseller() && ! $request->user()->isAdmin()) {
            if ($validated['role'] !== 'user') {
                abort(403, 'Bayiler yalnızca son kullanıcı oluşturabilir.');
            }
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

        $user->assignRole($validated['role']);

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
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$user->id,
            'status' => 'sometimes|string|in:active,suspended,pending,disabled',
            'hosting_package_id' => 'nullable|exists:hosting_packages,id',
            'locale' => 'nullable|string',
            'sync_hosting_package_from_billing' => 'sometimes|boolean',
        ]);

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
            $user->update($validated);
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
}
