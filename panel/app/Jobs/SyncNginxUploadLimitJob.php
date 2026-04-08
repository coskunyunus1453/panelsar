<?php

namespace App\Jobs;

use App\Services\EngineApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncNginxUploadLimitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $runId,
        private readonly int $limitMb,
        private readonly string $scope,
    ) {}

    public function handle(EngineApiService $engine): void
    {
        $cacheKey = $this->cacheKey();
        $ttl = now()->addMinutes(30);

        $steps = [
            ['key' => 'read_config', 'ok' => false, 'message' => 'Reading Nginx config'],
            ['key' => 'patch_config', 'ok' => false, 'message' => 'Updating client_max_body_size'],
            ['key' => 'test_reload', 'ok' => false, 'message' => 'Testing and reloading Nginx'],
        ];

        $desired = "client_max_body_size {$this->limitMb}m;";
        $failedStep = null;

        try {
            $state = Cache::get($cacheKey, []);
            $steps = is_array($state['steps'] ?? null) ? $state['steps'] : $steps;

            Cache::put($cacheKey, [
                'run_id' => $this->runId,
                'status' => 'running',
                'progress' => 5,
                'steps' => $steps,
            ], $ttl);

            // Step 1: read config
            $failedStep = 'read_config';
            $cfg = $engine->getNginxConfig($this->scope);
            if (! empty($cfg['error'])) {
                throw new \RuntimeException((string) $cfg['error']);
            }
            $content = (string) ($cfg['content'] ?? '');
            if (trim($content) === '') {
                throw new \RuntimeException('Nginx config content not found.');
            }
            $this->markStep($steps, 'read_config', true);
            Cache::put($cacheKey, [
                'run_id' => $this->runId,
                'status' => 'running',
                'progress' => 35,
                'steps' => $steps,
            ], $ttl);

            // Step 2: patch config
            $failedStep = 'patch_config';
            if (preg_match('/client_max_body_size\s+\S+;/i', $content) === 1) {
                $next = (string) preg_replace('/client_max_body_size\s+\S+;/i', $desired, $content);
            } elseif (preg_match('/server_name\s+[^;\n]+;\s*/i', $content) === 1) {
                $next = (string) preg_replace(
                    '/(server_name\s+[^;\n]+;\s*\n)/i',
                    "$1    {$desired}\n",
                    $content,
                    1
                );
            } else {
                $next = $content."\n    ".$desired."\n";
            }
            $this->markStep($steps, 'patch_config', true);
            Cache::put($cacheKey, [
                'run_id' => $this->runId,
                'status' => 'running',
                'progress' => 65,
                'steps' => $steps,
            ], $ttl);

            // Step 3: update + test/reload (engine test_reload param)
            $failedStep = 'test_reload';
            $updated = $engine->updateNginxConfig($this->scope, $next, true);
            if (! empty($updated['error'])) {
                throw new \RuntimeException((string) $updated['error']);
            }
            $this->markStep($steps, 'test_reload', true);

            Cache::put($cacheKey, [
                'run_id' => $this->runId,
                'status' => 'success',
                'progress' => 100,
                'steps' => $steps,
                'message' => 'Nginx upload limiti güncellendi.',
                'scope' => $this->scope,
                'client_max_body_size_mb' => $this->limitMb,
            ], $ttl);
        } catch (Throwable $e) {
            $this->markStep($steps, $failedStep ?? 'test_reload', false);
            Cache::put($cacheKey, [
                'run_id' => $this->runId,
                'status' => 'failed',
                'progress' => 100,
                'steps' => $steps,
                'failed_step' => $failedStep,
                'message' => $e->getMessage(),
                'scope' => $this->scope,
            ], $ttl);
        }
    }

    /**
     * @param  list<array{key:string,ok:bool,message:string}>  $steps
     * @return void
     */
    private function markStep(array &$steps, string $key, bool $ok): void
    {
        foreach ($steps as &$s) {
            if (($s['key'] ?? null) === $key) {
                $s['ok'] = $ok;
                return;
            }
        }
    }

    private function cacheKey(): string
    {
        return 'admin:php:nginx-upload-sync:'.$this->runId;
    }
}

