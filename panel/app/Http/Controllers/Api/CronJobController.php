<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CronJob;
use App\Models\CronJobRun;
use App\Services\EngineApiService;
use App\Services\HostingQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

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

    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'quota' => $this->quota->cronQuotaSummary($request->user()),
            'timezone_hint' => config('app.timezone', 'UTC'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule' => ['required', 'string', 'max:80', $this->cronScheduleRule()],
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

    public function update(Request $request, CronJob $cronJob): JsonResponse
    {
        $this->assertCanAccess($request, $cronJob);

        $validated = $request->validate([
            'schedule' => ['required', 'string', 'max:80', $this->cronScheduleRule()],
            'command' => 'required|string|max:2000',
            'description' => 'nullable|string|max:255',
        ]);

        $eid = $cronJob->engine_job_id;
        if ($eid === null || $eid === '') {
            $eid = (string) $cronJob->id;
        }

        $engine = $this->engine->engineCronUpdate($eid, [
            'schedule' => $validated['schedule'],
            'command' => $validated['command'],
            'description' => $validated['description'] ?? '',
        ]);

        if (! empty($engine['error'])) {
            return response()->json([
                'message' => $engine['error'],
                'engine' => $engine,
            ], 502);
        }

        $cronJob->update([
            'schedule' => $validated['schedule'],
            'command' => $validated['command'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'message' => __('cron.updated'),
            'job' => $cronJob->fresh(),
        ]);
    }

    public function destroy(Request $request, CronJob $cronJob): JsonResponse
    {
        $this->assertCanAccess($request, $cronJob);
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

    public function runNow(Request $request, CronJob $cronJob): JsonResponse
    {
        $this->assertCanAccess($request, $cronJob);

        $run = CronJobRun::create([
            'cron_job_id' => $cronJob->id,
            'user_id' => $request->user()->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $process = Process::fromShellCommandline($cronJob->command);
        $process->setTimeout(180);
        $process->setIdleTimeout(120);

        try {
            $process->mustRun();
            $run->update([
                'status' => 'success',
                'exit_code' => $process->getExitCode(),
                'output' => trim($process->getOutput()."\n".$process->getErrorOutput()),
                'finished_at' => now(),
            ]);
        } catch (ProcessTimedOutException $e) {
            $run->update([
                'status' => 'timeout',
                'exit_code' => $process->getExitCode(),
                'output' => trim(($process->getOutput() ?? '')."\n".($process->getErrorOutput() ?? '')."\n".$e->getMessage()),
                'finished_at' => now(),
            ]);

            return response()->json([
                'message' => __('cron.run_timeout'),
                'run' => $run->fresh(),
            ], 408);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'exit_code' => $process->getExitCode(),
                'output' => trim(($process->getOutput() ?? '')."\n".($process->getErrorOutput() ?? '')."\n".$e->getMessage()),
                'finished_at' => now(),
            ]);

            return response()->json([
                'message' => __('cron.run_failed'),
                'run' => $run->fresh(),
            ], 422);
        }

        return response()->json([
            'message' => __('cron.run_success'),
            'run' => $run->fresh(),
        ]);
    }

    public function runs(Request $request, CronJob $cronJob): JsonResponse
    {
        $this->assertCanAccess($request, $cronJob);
        $runs = $cronJob->runs()->latest()->limit(20)->get();

        return response()->json([
            'data' => $runs,
        ]);
    }

    /**
     * @return \Closure(string, mixed, \Closure): void
     */
    private function cronScheduleRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value)) {
                $fail(__('cron.invalid_schedule'));

                return;
            }
            $parts = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
            if ($parts === false || count($parts) !== 5) {
                $fail(__('cron.invalid_schedule'));
            }
        };
    }

    private function assertCanAccess(Request $request, CronJob $cronJob): void
    {
        if ($cronJob->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
    }
}
