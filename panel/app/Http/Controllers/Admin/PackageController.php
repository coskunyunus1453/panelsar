<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HostingPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(): JsonResponse
    {
        $packages = HostingPackage::orderBy('sort_order')->get();

        return response()->json(['packages' => $packages]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:hosting_packages',
            'description' => 'nullable|string',
            'disk_space_mb' => 'required|integer|min:-1',
            'bandwidth_mb' => 'required|integer|min:-1',
            'max_domains' => 'required|integer|min:-1',
            'max_subdomains' => 'required|integer|min:-1',
            'max_databases' => 'required|integer|min:-1',
            'max_email_accounts' => 'required|integer|min:-1',
            'max_ftp_accounts' => 'required|integer|min:-1',
            'max_cron_jobs' => 'required|integer|min:-1',
            'cpu_limit' => 'nullable|integer',
            'memory_limit_mb' => 'nullable|integer',
            'php_versions' => 'nullable|array',
            'ssl_enabled' => 'boolean',
            'backup_enabled' => 'boolean',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
        ]);

        $package = HostingPackage::create($validated);

        return response()->json([
            'message' => __('packages.created'),
            'package' => $package,
        ], 201);
    }

    public function update(Request $request, HostingPackage $package): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'disk_space_mb' => 'sometimes|integer|min:-1',
            'bandwidth_mb' => 'sometimes|integer|min:-1',
            'max_domains' => 'sometimes|integer|min:-1',
            'price_monthly' => 'sometimes|numeric|min:0',
            'price_yearly' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $package->update($validated);

        return response()->json([
            'message' => __('packages.updated'),
            'package' => $package->fresh(),
        ]);
    }

    public function destroy(HostingPackage $package): JsonResponse
    {
        if ($package->users()->exists()) {
            return response()->json([
                'message' => __('packages.has_users'),
            ], 422);
        }

        $package->delete();

        return response()->json(['message' => __('packages.deleted')]);
    }
}
