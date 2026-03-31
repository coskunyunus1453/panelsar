<?php

namespace App\Jobs;

use App\Models\InstallerRun;
use App\Services\EngineApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunInstallerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $runId,
        private readonly string $domainName,
        private readonly string $app,
        private readonly array $payload
    ) {}

    public function handle(EngineApiService $engine): void
    {
        $run = InstallerRun::query()->find($this->runId);
        if (! $run) {
            return;
        }

        $run->status = 'running';
        $run->started_at = now();
        $run->message = __('installer.started');
        $run->save();

        $result = $engine->installerRun($this->app, $this->domainName, $this->payload);
        if (! empty($result['error'])) {
            $run->status = 'failed';
            $run->message = (string) $result['error'];
            $run->output = is_string($result['output'] ?? null) ? $result['output'] : null;
            $run->finished_at = now();
            $run->save();
            return;
        }

        $run->status = 'success';
        $run->message = __('installer.completed');
        $run->output = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $run->finished_at = now();
        $run->save();
    }
}
