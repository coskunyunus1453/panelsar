<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\Domain;
use App\Services\EngineApiService;
use App\Services\HostingQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ]);
        $domain = Domain::findOrFail($validated['domain_id']);
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $this->quota->ensureCanQueueBackup($request->user());

        $backup = Backup::create([
            'user_id' => $request->user()->id,
            'domain_id' => $domain->id,
            'type' => $validated['type'] ?? 'full',
            'status' => 'pending',
        ]);

        $engine = $this->engine->queueBackup($domain->name, $backup->type, $backup->id);
        if (! empty($engine['error'])) {
            $backup->update(['status' => 'failed']);

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

        return response()->json(['message' => __('backups.queued'), 'backup' => $backup->fresh(), 'engine' => $engine], 202);
    }

    public function destroy(Request $request, Backup $backup): JsonResponse
    {
        if ($backup->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
        $backup->delete();

        return response()->json(['message' => __('backups.deleted')]);
    }

    public function restore(Request $request, Backup $backup): JsonResponse
    {
        if ($backup->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
        $eid = $backup->engine_backup_id;
        if ($eid === null || $eid === '') {
            return response()->json(['message' => __('backups.restore_no_engine_id')], 422);
        }
        $result = $this->engine->restoreBackup($eid);

        return response()->json(['message' => __('backups.restore_started'), 'engine' => $result]);
    }

    public function engineSnapshot(Request $request): JsonResponse
    {
        return response()->json(['remote' => $this->engine->listBackups()]);
    }
}
