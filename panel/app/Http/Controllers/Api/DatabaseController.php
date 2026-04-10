<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Database;
use App\Models\Domain;
use App\Services\DatabaseService;
use App\Services\HostingQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PDOException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DatabaseController extends Controller
{
    public function __construct(
        private DatabaseService $databaseService,
        private HostingQuotaService $quota,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $databases = $request->user()->databases()->latest()->paginate(20);
        $this->databaseService->hydrateDatabaseSizesOnPaginator($databases);

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

        if (! empty($validated['domain_id'])) {
            $ownsDomain = Domain::query()
                ->where('id', (int) $validated['domain_id'])
                ->where('user_id', $request->user()->id)
                ->exists();
            if (! $ownsDomain) {
                abort(403);
            }
        }

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
        } catch (PDOException $e) {
            report($e);

            return response()->json([
                'message' => __('databases.provision_failed').': '.$e->getMessage(),
            ], 503);
        } catch (Throwable $e) {
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
        $validated = $request->validate([
            'grant_host' => 'nullable|string|max:64',
            'password' => 'nullable|string|min:8|max:255',
        ]);

        $grantHost = isset($validated['grant_host']) ? trim((string) $validated['grant_host']) : null;
        $password = isset($validated['password']) ? trim((string) $validated['password']) : null;

        $hasPassword = $password !== null && $password !== '';
        $grantProvided = $grantHost !== null && $grantHost !== '';
        $grantChanged = $database->type === 'mysql'
            && $grantProvided
            && $grantHost !== $database->mysqlGrantHost();

        if (! $hasPassword && ! $grantChanged) {
            return response()->json(['message' => __('databases.update_no_changes')], 422);
        }

        if ($grantProvided && $database->type !== 'mysql') {
            return response()->json(['message' => __('databases.grant_host_mysql_only')], 422);
        }

        try {
            if ($hasPassword) {
                $result = $this->databaseService->updateCredentials(
                    $database,
                    $password,
                    $database->type === 'mysql' ? $grantHost : null,
                );
            } else {
                $this->databaseService->updateGrantHost($database, (string) $grantHost);
                $result = [];
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (PDOException $e) {
            report($e);

            return response()->json([
                'message' => __('databases.provision_failed').': '.$e->getMessage(),
            ], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() ?: __('databases.provision_failed'),
            ], 500);
        }

        $payload = [
            'message' => __('databases.updated'),
            'database' => $database->fresh(),
            'password_plain' => $result['password_plain'] ?? null,
        ];
        if (! empty($result['app_config_reminder'])) {
            $payload['sync_reminder'] = (string) __('databases.credentials_sync_reminder');
        }

        return response()->json($payload);
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
        } catch (PDOException $e) {
            report($e);

            return response()->json([
                'message' => __('databases.provision_failed').': '.$e->getMessage(),
            ], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() ?: __('databases.provision_failed'),
            ], 500);
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

        try {
            $this->databaseService->delete($database);
        } catch (PDOException $e) {
            report($e);

            return response()->json([
                'message' => __('databases.provision_failed').': '.$e->getMessage(),
            ], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() ?: __('databases.provision_failed'),
            ], 500);
        }

        return response()->json(['message' => __('databases.deleted')]);
    }

    public function export(Request $request, Database $database): JsonResponse|StreamedResponse
    {
        $this->authorize('export', $database);

        if (! in_array($database->type, ['mysql', 'postgresql'], true)) {
            return response()->json(['message' => __('databases.export_unsupported_type')], 422);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $database->name) ?: 'database';
        $filename = $safeName.'_'.date('Y-m-d_His').'.sql';

        try {
            return response()->streamDownload(function () use ($database): void {
                if ($database->type === 'mysql') {
                    $this->databaseService->streamMysqlDump($database, function (string $chunk): void {
                        echo $chunk;
                        if (ob_get_level() > 0) {
                            @ob_flush();
                        }
                        flush();
                    });
                } else {
                    $this->databaseService->streamPostgresDump($database, function (string $chunk): void {
                        echo $chunk;
                        if (ob_get_level() > 0) {
                            @ob_flush();
                        }
                        flush();
                    });
                }
            }, $filename, [
                'Content-Type' => 'application/sql; charset=UTF-8',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() ?: __('databases.export_failed'),
            ], 500);
        }
    }

    public function import(Request $request, Database $database): JsonResponse
    {
        $this->authorize('import', $database);

        if (! in_array($database->type, ['mysql', 'postgresql'], true)) {
            return response()->json(['message' => __('databases.import_unsupported_type')], 422);
        }

        $maxMb = max(1, (int) config('hostvim.limits.max_db_import_mb', 512));
        $maxKb = $maxMb * 1024;

        $validated = $request->validate([
            'sql_file' => ['required', 'file', 'max:'.$maxKb],
            'confirmation' => ['required', 'string', 'max:128'],
        ]);

        $expected = (string) __('databases.import_confirm_expected');
        if (trim((string) $validated['confirmation']) !== $expected) {
            return response()->json(['message' => __('databases.import_confirm_mismatch')], 422);
        }

        $upload = $request->file('sql_file');
        $ext = strtolower((string) $upload->getClientOriginalExtension());
        if ($ext !== 'sql') {
            return response()->json(['message' => __('databases.import_sql_only')], 422);
        }

        $path = $upload->getRealPath();
        if ($path === false || ! is_readable($path)) {
            return response()->json(['message' => __('databases.import_file_unreadable')], 422);
        }

        try {
            if ($database->type === 'mysql') {
                $this->databaseService->importMysqlFromSqlFile($database, $path);
            } else {
                $this->databaseService->importPostgresFromSqlFile($database, $path);
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() ?: __('databases.import_failed'),
            ], 500);
        }

        return response()->json(['message' => __('databases.imported')]);
    }
}
