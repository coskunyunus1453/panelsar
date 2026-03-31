<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\BackupDestination;
use App\Models\BackupSchedule;
use App\Models\Domain;
use App\Services\EngineApiService;
use App\Services\HostingQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BackupController extends Controller
{
    use AuthorizesUserDomain;

    public function __construct(
        private EngineApiService $engine,
        private HostingQuotaService $quota,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $backups = $request->user()->backups()->with('domain')->latest()->paginate(20);

        return response()->json($backups);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain_id' => 'required|exists:domains,id',
            'type' => 'nullable|string|in:full,files,database',
            'destination_id' => 'nullable|integer|exists:backup_destinations,id',
        ]);
        $domain = Domain::findOrFail($validated['domain_id']);
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        if (! empty($validated['destination_id'])) {
            $ownsDestination = BackupDestination::query()
                ->where('id', (int) $validated['destination_id'])
                ->where('user_id', $request->user()->id)
                ->exists();
            if (! $ownsDestination) {
                abort(403);
            }
        }

        $this->quota->ensureCanQueueBackup($request->user());

        $backup = Backup::create([
            'user_id' => $request->user()->id,
            'domain_id' => $domain->id,
            'destination_id' => $validated['destination_id'] ?? null,
            'type' => $validated['type'] ?? 'full',
            'status' => 'pending',
        ]);

        $engine = $this->engine->queueBackup($domain->name, $backup->type, $backup->id);
        if (! empty($engine['error'])) {
            $backup->update(['status' => 'failed']);
            $this->audit($request, 'backup_queue', false, (string) $engine['error'], [
                'domain_id' => $domain->id,
                'backup_id' => $backup->id,
            ]);

            return response()->json([
                'message' => (string) $engine['error'],
                'backup' => $backup->fresh(),
            ], 502);
        }

        $engineId = isset($engine['id']) ? (string) $engine['id'] : null;
        $backup->update([
            'status' => 'running',
            'file_path' => $engine['path'] ?? null,
            'engine_backup_id' => $engineId,
        ]);
        $this->audit($request, 'backup_queue', true, null, [
            'domain_id' => $domain->id,
            'backup_id' => $backup->id,
            'destination_id' => $backup->destination_id,
        ]);

        return response()->json(['message' => __('backups.queued'), 'backup' => $backup->fresh(), 'engine' => $engine], 202);
    }

    public function destinations(Request $request): JsonResponse
    {
        $rows = BackupDestination::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->get();
        return response()->json(['destinations' => $rows]);
    }

    public function storeDestination(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'driver' => ['required', 'string', Rule::in(['local', 's3', 'ftp'])],
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'config' => 'nullable|array',
        ]);
        if (($validated['is_default'] ?? false) === true) {
            BackupDestination::query()->where('user_id', $request->user()->id)->update(['is_default' => false]);
        }
        $dest = BackupDestination::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'driver' => $validated['driver'],
            'config' => $validated['config'] ?? [],
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);
        $this->audit($request, 'backup_destination_create', true, null, ['destination_id' => $dest->id]);
        return response()->json(['message' => __('backups.destination_saved'), 'destination' => $dest], 201);
    }

    public function updateDestination(Request $request, BackupDestination $backupDestination): JsonResponse
    {
        if ($backupDestination->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'driver' => ['sometimes', 'string', Rule::in(['local', 's3', 'ftp'])],
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'config' => 'nullable|array',
        ]);
        if (($validated['is_default'] ?? false) === true) {
            BackupDestination::query()->where('user_id', $backupDestination->user_id)->update(['is_default' => false]);
        }
        $backupDestination->fill($validated);
        $backupDestination->save();
        $this->audit($request, 'backup_destination_update', true, null, ['destination_id' => $backupDestination->id]);
        return response()->json(['message' => __('backups.destination_saved'), 'destination' => $backupDestination->fresh()]);
    }

    public function destroyDestination(Request $request, BackupDestination $backupDestination): JsonResponse
    {
        if ($backupDestination->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
        $id = $backupDestination->id;
        $backupDestination->delete();
        $this->audit($request, 'backup_destination_delete', true, null, ['destination_id' => $id]);
        return response()->json(['message' => __('backups.deleted')]);
    }

    public function schedules(Request $request): JsonResponse
    {
        $rows = BackupSchedule::query()
            ->where('user_id', $request->user()->id)
            ->with(['domain:id,name', 'destination:id,name,driver'])
            ->latest('id')
            ->get();
        return response()->json(['schedules' => $rows]);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain_id' => 'required|integer|exists:domains,id',
            'destination_id' => 'nullable|integer|exists:backup_destinations,id',
            'type' => 'nullable|string|in:full,files,database',
            'schedule' => ['required', 'string', 'regex:/^\S+\s+\S+\s+\S+\s+\S+\s+\S+$/'],
            'enabled' => 'sometimes|boolean',
        ]);
        $domain = Domain::findOrFail($validated['domain_id']);
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        if (! empty($validated['destination_id'])) {
            $ownsDestination = BackupDestination::query()
                ->where('id', (int) $validated['destination_id'])
                ->where('user_id', $request->user()->id)
                ->exists();
            if (! $ownsDestination) {
                abort(403);
            }
        }
        $row = BackupSchedule::create([
            'user_id' => $request->user()->id,
            'domain_id' => $domain->id,
            'destination_id' => $validated['destination_id'] ?? null,
            'type' => $validated['type'] ?? 'full',
            'schedule' => $validated['schedule'],
            'enabled' => (bool) ($validated['enabled'] ?? true),
        ]);
        $this->audit($request, 'backup_schedule_create', true, null, ['schedule_id' => $row->id, 'domain_id' => $domain->id]);
        return response()->json(['message' => __('backups.schedule_saved'), 'schedule' => $row->fresh(['domain:id,name', 'destination:id,name,driver'])], 201);
    }

    public function updateSchedule(Request $request, BackupSchedule $backupSchedule): JsonResponse
    {
        if ($backupSchedule->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
        $validated = $request->validate([
            'destination_id' => 'nullable|integer|exists:backup_destinations,id',
            'type' => 'nullable|string|in:full,files,database',
            'schedule' => ['sometimes', 'string', 'regex:/^\S+\s+\S+\s+\S+\s+\S+\s+\S+$/'],
            'enabled' => 'sometimes|boolean',
        ]);
        if (! empty($validated['destination_id'])) {
            $ownsDestination = BackupDestination::query()
                ->where('id', (int) $validated['destination_id'])
                ->where('user_id', $request->user()->id)
                ->exists();
            if (! $ownsDestination) {
                abort(403);
            }
        }
        $backupSchedule->fill($validated);
        $backupSchedule->save();
        $this->audit($request, 'backup_schedule_update', true, null, ['schedule_id' => $backupSchedule->id]);
        return response()->json(['message' => __('backups.schedule_saved'), 'schedule' => $backupSchedule->fresh(['domain:id,name', 'destination:id,name,driver'])]);
    }

    public function destroySchedule(Request $request, BackupSchedule $backupSchedule): JsonResponse
    {
        if ($backupSchedule->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
        $id = $backupSchedule->id;
        $backupSchedule->delete();
        $this->audit($request, 'backup_schedule_delete', true, null, ['schedule_id' => $id]);
        return response()->json(['message' => __('backups.deleted')]);
    }

    public function runSchedule(Request $request, BackupSchedule $backupSchedule): JsonResponse
    {
        if ($backupSchedule->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
        $domain = $backupSchedule->domain()->first();
        if (! $domain) {
            return response()->json(['message' => 'domain not found'], 422);
        }

        $this->quota->ensureCanQueueBackup($request->user());

        $backup = Backup::create([
            'user_id' => $request->user()->id,
            'domain_id' => $domain->id,
            'destination_id' => $backupSchedule->destination_id,
            'type' => $backupSchedule->type ?: 'full',
            'status' => 'pending',
        ]);
        $engine = $this->engine->queueBackup($domain->name, $backup->type, $backup->id);
        if (! empty($engine['error'])) {
            $backup->update(['status' => 'failed']);
            $this->audit($request, 'backup_schedule_run', false, (string) $engine['error'], ['schedule_id' => $backupSchedule->id, 'backup_id' => $backup->id]);
            return response()->json(['message' => (string) $engine['error']], 502);
        }
        $backupSchedule->update(['last_run_at' => now()]);
        $backup->update([
            'status' => 'running',
            'file_path' => $engine['path'] ?? null,
            'engine_backup_id' => isset($engine['id']) ? (string) $engine['id'] : null,
        ]);
        if ($backup->destination_id) {
            $sync = $this->syncToDestination($backup);
            if (! $sync['ok']) {
                $this->audit($request, 'backup_schedule_sync', false, $sync['error'] ?? 'sync failed', ['backup_id' => $backup->id]);
            }
        }
        $this->audit($request, 'backup_schedule_run', true, null, ['schedule_id' => $backupSchedule->id, 'backup_id' => $backup->id]);
        return response()->json(['message' => __('backups.queued'), 'backup' => $backup->fresh()]);
    }

    public function destroy(Request $request, Backup $backup): JsonResponse
    {
        if ($backup->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
        $backup->delete();
        $this->audit($request, 'backup_delete', true, null, ['backup_id' => $backup->id]);

        return response()->json(['message' => __('backups.deleted')]);
    }

    public function restore(Request $request, Backup $backup): JsonResponse
    {
        if ($backup->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
        $validated = $request->validate([
            'source' => 'nullable|string|in:engine,remote',
            'destination_id' => 'nullable|integer|exists:backup_destinations,id',
            'backup_set' => 'nullable|string|max:255',
        ]);
        $source = (string) ($validated['source'] ?? 'engine');
        $eid = $backup->engine_backup_id;

        if ($source === 'remote') {
            $destId = $validated['destination_id'] ?? $backup->destination_id;
            $set = (string) ($validated['backup_set'] ?? '');
            if (! $destId || trim($set) === '') {
                return response()->json(['message' => __('backups.remote_restore_missing')], 422);
            }
            $ownsDestination = BackupDestination::query()
                ->where('id', (int) $destId)
                ->where('user_id', $request->user()->id)
                ->exists();
            if (! $ownsDestination) {
                abort(403);
            }
            // Bu adım engine restore-file endpointi gelene kadar referans üretir.
            $result = $this->engine->restoreBackup($eid ?: $set);
            $this->audit($request, 'backup_restore_remote', true, null, [
                'backup_id' => $backup->id,
                'destination_id' => $destId,
                'backup_set' => $set,
            ]);
            return response()->json(['message' => __('backups.restore_started'), 'engine' => $result]);
        }

        if ($eid === null || $eid === '') {
            return response()->json(['message' => __('backups.restore_no_engine_id')], 422);
        }
        $result = $this->engine->restoreBackup($eid);
        $this->audit($request, 'backup_restore', true, null, ['backup_id' => $backup->id, 'engine_backup_id' => $eid]);

        return response()->json(['message' => __('backups.restore_started'), 'engine' => $result]);
    }

    public function engineSnapshot(Request $request): JsonResponse
    {
        $rows = $this->engine->listBackups();
        if ($request->user()->isAdmin()) {
            return response()->json(['remote' => $rows]);
        }

        $allowedDomains = Domain::query()
            ->where('user_id', $request->user()->id)
            ->pluck('name')
            ->map(static fn ($v) => strtolower(trim((string) $v)))
            ->filter()
            ->values()
            ->all();
        $allowedEngineIds = Backup::query()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('engine_backup_id')
            ->pluck('engine_backup_id')
            ->map(static fn ($v) => trim((string) $v))
            ->filter()
            ->values()
            ->all();
        $allowedPanelIds = Backup::query()
            ->where('user_id', $request->user()->id)
            ->pluck('id')
            ->map(static fn ($v) => (string) $v)
            ->values()
            ->all();

        $filtered = array_values(array_filter($rows, static function ($row) use ($allowedDomains, $allowedEngineIds, $allowedPanelIds): bool {
            if (! is_array($row)) {
                return false;
            }
            $domain = strtolower(trim((string) ($row['domain'] ?? '')));
            if ($domain !== '' && in_array($domain, $allowedDomains, true)) {
                return true;
            }
            $engineId = trim((string) ($row['id'] ?? ($row['engine_backup_id'] ?? '')));
            if ($engineId !== '' && in_array($engineId, $allowedEngineIds, true)) {
                return true;
            }
            $panelBackupId = trim((string) ($row['panel_backup_id'] ?? ''));
            return $panelBackupId !== '' && in_array($panelBackupId, $allowedPanelIds, true);
        }));

        return response()->json(['remote' => $filtered]);
    }

    public function sync(Request $request, Backup $backup): JsonResponse
    {
        if ($backup->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
        $result = $this->syncToDestination($backup);
        if (! $result['ok']) {
            $this->audit($request, 'backup_sync', false, $result['error'] ?? 'sync failed', ['backup_id' => $backup->id]);
            return response()->json(['message' => $result['error'] ?? 'sync failed'], 422);
        }
        $this->audit($request, 'backup_sync', true, null, ['backup_id' => $backup->id]);
        return response()->json(['message' => __('backups.synced'), 'remote_path' => $result['remote_path'] ?? null]);
    }

    /**
     * @return array{ok: bool, error?: string, remote_path?: string}
     */
    public function syncToDestination(Backup $backup): array
    {
        if (! $backup->destination_id) {
            return ['ok' => false, 'error' => 'destination not selected'];
        }
        $dest = BackupDestination::query()->find($backup->destination_id);
        if (! $dest || ! $dest->is_active) {
            return ['ok' => false, 'error' => 'destination not active'];
        }
        $sourcePath = trim((string) $backup->file_path);
        if ($sourcePath === '' || ! is_file($sourcePath)) {
            return ['ok' => false, 'error' => 'backup file not found'];
        }
        $baseName = basename($sourcePath);
        $cfg = (array) ($dest->config ?? []);
        $remotePath = trim((string) ($cfg['path'] ?? 'backups')).'/'.$baseName;
        $remotePath = ltrim(str_replace('\\', '/', $remotePath), '/');

        try {
            $disk = $this->buildDestinationDisk($dest);
            $stream = fopen($sourcePath, 'rb');
            if ($stream === false) {
                return ['ok' => false, 'error' => 'backup stream open failed'];
            }
            $ok = $disk->put($remotePath, $stream);
            fclose($stream);
            if (! $ok) {
                return ['ok' => false, 'error' => 'remote write failed'];
            }
            return ['ok' => true, 'remote_path' => $remotePath];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildDestinationDisk(BackupDestination $dest)
    {
        $cfg = (array) ($dest->config ?? []);
        if ($dest->driver === 's3') {
            return Storage::build([
                'driver' => 's3',
                'key' => (string) ($cfg['access_key'] ?? ''),
                'secret' => (string) ($cfg['secret_key'] ?? ''),
                'region' => (string) ($cfg['region'] ?? 'us-east-1'),
                'bucket' => (string) ($cfg['bucket'] ?? ''),
                'throw' => true,
            ]);
        }
        if ($dest->driver === 'ftp') {
            return Storage::build([
                'driver' => 'ftp',
                'host' => (string) ($cfg['host'] ?? ''),
                'username' => (string) ($cfg['username'] ?? ''),
                'password' => (string) ($cfg['password'] ?? ''),
                'root' => (string) ($cfg['path'] ?? '/'),
                'throw' => true,
            ]);
        }
        return Storage::build([
            'driver' => 'local',
            'root' => (string) ($cfg['path'] ?? storage_path('app/backups')),
            'throw' => true,
        ]);
    }

    private function audit(Request $request, string $action, bool $success, ?string $error = null, array $extra = []): void
    {
        Log::info('panelsar.backup_audit', array_merge([
            'action' => $action,
            'user_id' => $request->user()?->id,
            'success' => $success,
            'error' => $error,
            'ip' => $request->ip(),
        ], $extra));
    }
}
