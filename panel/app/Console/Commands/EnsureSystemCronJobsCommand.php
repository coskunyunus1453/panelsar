<?php

namespace App\Console\Commands;

use App\Models\CronJob;
use App\Models\User;
use App\Services\EngineApiService;
use Illuminate\Console\Command;

class EnsureSystemCronJobsCommand extends Command
{
    protected $signature = 'hostvim:ensure-system-cron';

    protected $description = 'Ensure protected system cron jobs exist and are synced to engine';

    public function handle(EngineApiService $engine): int
    {
        $owner = User::query()
            ->whereHas('roles', static fn ($q) => $q->where('name', 'admin'))
            ->orderBy('id')
            ->first();

        if (! $owner) {
            $owner = User::query()->orderBy('id')->first();
        }
        if (! $owner) {
            $this->warn('No users found; system cron creation skipped.');

            return self::SUCCESS;
        }

        $jobs = [
            [
                'key' => 'panel.scheduler.run',
                'schedule' => '* * * * *',
                'command' => '/usr/bin/php '.base_path('artisan').' schedule:run >> /dev/null 2>&1',
                'description' => 'Hostvim system scheduler (protected)',
            ],
        ];

        foreach ($jobs as $spec) {
            $job = CronJob::query()->firstOrNew(['system_key' => $spec['key']]);
            $job->fill([
                'user_id' => $owner->id,
                'schedule' => $spec['schedule'],
                'command' => $spec['command'],
                'description' => $spec['description'],
                'status' => 'active',
                'is_system' => true,
                'system_key' => $spec['key'],
            ]);
            $job->save();

            if ($job->engine_job_id) {
                $resp = $engine->engineCronUpdate((string) $job->engine_job_id, [
                    'schedule' => $job->schedule,
                    'command' => $job->command,
                    'description' => $job->description ?? '',
                ]);
                if (! empty($resp['error'])) {
                    $this->warn('Engine cron update failed for '.$spec['key'].': '.$resp['error']);
                }

                continue;
            }

            $resp = $engine->engineCronCreate([
                'schedule' => $job->schedule,
                'command' => $job->command,
                'user_id' => $job->user_id,
                'panel_job_id' => $job->id,
            ]);
            if (! empty($resp['id'])) {
                $job->engine_job_id = (string) $resp['id'];
                $job->save();
            } elseif (! empty($resp['error'])) {
                $this->warn('Engine cron create failed for '.$spec['key'].': '.$resp['error']);
            }
        }

        $this->info('System cron jobs ensured.');

        return self::SUCCESS;
    }
}
