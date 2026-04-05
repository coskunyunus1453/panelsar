<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\BackupController;
use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Services\EngineApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class BackupsRunDueCommand extends Command
{
    protected $signature = 'backups:run-due';

    protected $description = 'Run due backup schedules';

    public function handle(EngineApiService $engine): int
    {
        $now = now();
        $rows = BackupSchedule::query()
            ->where('enabled', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now);
            })
            ->with(['domain', 'user'])
            ->limit(50)
            ->get();

        /** @var BackupController $controller */
        $controller = App::make(BackupController::class);

        foreach ($rows as $s) {
            $domain = $s->domain;
            if (! $domain || ! $s->user) {
                continue;
            }
            $backup = Backup::create([
                'user_id' => $s->user_id,
                'domain_id' => $s->domain_id,
                'destination_id' => $s->destination_id,
                'type' => $s->type ?: 'full',
                'status' => 'pending',
            ]);
            $res = $engine->queueBackup($domain->name, $backup->type, $backup->id);
            if (! empty($res['error'])) {
                $backup->update(['status' => 'failed']);
                Log::warning('hostvim.backup_schedule_due_failed', [
                    'schedule_id' => $s->id,
                    'backup_id' => $backup->id,
                    'error' => $res['error'],
                ]);
            } else {
                $engineStatus = is_string($res['status'] ?? null) ? (string) $res['status'] : '';
                $panelStatus = $engineStatus === 'completed' || $engineStatus === 'failed' ? $engineStatus : 'running';
                $upd = [
                    'status' => $panelStatus,
                    'file_path' => $res['path'] ?? null,
                    'engine_backup_id' => isset($res['id']) ? (string) $res['id'] : null,
                ];
                if (! empty($res['size_bytes'])) {
                    $upd['size_mb'] = round(((float) $res['size_bytes']) / 1048576, 4);
                }
                if ($panelStatus === 'completed') {
                    $upd['completed_at'] = now();
                }
                $backup->update($upd);
                if ($panelStatus === 'completed') {
                    $controller->syncToDestination($backup);
                }
                $s->last_run_at = $now;
                $s->next_run_at = $now->copy()->addDay();
                $s->save();
            }
        }

        return self::SUCCESS;
    }
}
