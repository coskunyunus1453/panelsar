<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CronJob;
use App\Services\EngineApiService;
use App\Services\HostingQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CronJobController extends Controller
{
    public function __construct(
        private EngineApiService $engine,
        private HostingQuotaService $quota,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $jobs = $request->user()->cronJobs()->latest()->paginate(30);

        return response()->json($jobs);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule' => 'required|string|max:64',
            'command' => 'required|string|max:2000',
            'description' => 'nullable|string|max:255',
        ]);

        $this->quota->ensureCanCreateCronJob($request->user());

        $job = CronJob::create([
            'user_id' => $request->user()->id,
            'schedule' => $validated['schedule'],
            'command' => $validated['command'],
            'description' => $validated['description'] ?? null,
            'status' => 'active',
        ]);

        $engine = $this->engine->engineCronCreate([
            'schedule' => $job->schedule,
            'command' => $job->command,
            'user_id' => $job->user_id,
            'panel_job_id' => $job->id,
        ]);

        if (empty($engine['error']) && isset($engine['id']) && $engine['id'] !== '') {
            $job->update(['engine_job_id' => (string) $engine['id']]);
        }

        return response()->json([
            'message' => __('cron.created'),
            'job' => $job->fresh(),
            'engine' => $engine,
        ], 201);
    }

    public function destroy(Request $request, CronJob $cronJob): JsonResponse
    {
        if ($cronJob->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
        $eid = $cronJob->engine_job_id;
        if ($eid === null || $eid === '') {
            $eid = (string) $cronJob->id;
        }
        $cronJob->delete();

        return response()->json([
            'message' => __('cron.deleted'),
            'engine' => $this->engine->engineCronDelete($eid),
        ]);
    }
}
