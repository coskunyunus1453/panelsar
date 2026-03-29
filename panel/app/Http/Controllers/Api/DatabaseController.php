<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Database;
use App\Services\DatabaseService;
use App\Services\HostingQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PDOException;

class DatabaseController extends Controller
{
    public function __construct(
        private DatabaseService $databaseService,
        private HostingQuotaService $quota,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $databases = $request->user()->databases()->latest()->paginate(20);

        return response()->json($databases);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:64',
            'type' => 'nullable|string|in:mysql,postgresql',
            'domain_id' => 'nullable|exists:domains,id',
            'grant_host' => 'nullable|string|max:64',
        ]);

        $this->quota->ensureCanCreateDatabase($request->user());

        try {
            $result = $this->databaseService->create(
                $request->user(),
                $validated['name'],
                $validated['type'] ?? 'mysql',
                $validated['domain_id'] ?? null,
                $validated['grant_host'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\PDOException $e) {
            report($e);

            return response()->json([
                'message' => __('databases.provision_failed').': '.$e->getMessage(),
            ], 503);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage() ?: __('databases.provision_failed')], 500);
        }

        return response()->json([
            'message' => __('databases.created'),
            'database' => $result['database'],
            'password_plain' => $result['password_plain'],
        ], 201);
    }

    public function update(Request $request, Database $database): JsonResponse
    {
        $this->authorize('update', $database);

        if ($database->type !== 'mysql') {
            return response()->json(['message' => __('databases.grant_host_mysql_only')], 422);
        }

        $validated = $request->validate([
            'grant_host' => 'required|string|max:64',
        ]);

        try {
            $this->databaseService->updateGrantHost($database, $validated['grant_host']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => __('databases.updated'),
            'database' => $database->fresh(),
        ]);
    }

    public function rotatePassword(Request $request, Database $database): JsonResponse
    {
        $this->authorize('rotatePassword', $database);

        if (! in_array($database->type, ['mysql', 'postgresql'], true)) {
            return response()->json(['message' => __('databases.rotate_password_unsupported')], 422);
        }

        try {
            $result = $this->databaseService->rotatePassword($database);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => __('databases.password_rotated'),
            'database' => $database->fresh(),
            'password_plain' => $result['password_plain'],
        ]);
    }

    public function destroy(Request $request, Database $database): JsonResponse
    {
        $this->authorize('delete', $database);

        $this->databaseService->delete($database);

        return response()->json(['message' => __('databases.deleted')]);
    }
}
