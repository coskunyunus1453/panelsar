<?php

namespace App\Console\Commands;

use App\Models\SystemAlert;
use App\Services\EngineApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class RunSelfHealCommand extends Command
{
    protected $signature = 'hostvim:self-heal';

    protected $description = 'Run guarded self-heal checks and alerts';

    private const WINDOW_SECONDS = 300; // 5 min

    private const MAX_SAME_ACTION = 3;

    private const COOLDOWN_SECONDS = 120; // 2 min

    public function handle(EngineApiService $engine): int
    {
        $stats = $engine->getSystemStats();
        $services = $engine->getServices();

        $this->handleDiskAlert($stats);
        $this->handleServiceAutoRestart($engine, $services);

        return self::SUCCESS;
    }

    private function handleDiskAlert(array $stats): void
    {
        $disk = (float) ($stats['disk_percent'] ?? 0);
        if ($disk < 80) {
            return;
        }

        $level = $disk >= 90 ? 'error' : 'info';
        $dedupe = $disk >= 90 ? 'disk-high-90' : 'disk-high-80';
        $ttl = $disk >= 90 ? 900 : 1800; // 15m / 30m

        if (Cache::has('selfheal:alert:'.$dedupe)) {
            return;
        }
        Cache::put('selfheal:alert:'.$dedupe, true, now()->addSeconds($ttl));

        $this->createAlert([
            'level' => $level,
            'title' => $disk >= 90 ? 'Disk kullanimi kritik' : 'Disk kullanimi yuksek',
            'message' => sprintf('Disk kullanimi %.1f%%. Temizlik/backup stratejisi kontrol edilmelidir.', $disk),
            'path' => '/system',
            'dedupe_key' => $dedupe,
        ]);
    }

    /**
     * @param  array<int, array<string,mixed>>  $services
     */
    private function handleServiceAutoRestart(EngineApiService $engine, array $services): void
    {
        $byName = [];
        foreach ($services as $svc) {
            $name = (string) ($svc['name'] ?? '');
            if ($name !== '') {
                $byName[$name] = $svc;
            }
        }

        foreach (['nginx', 'apache2'] as $critical) {
            $status = strtolower((string) ($byName[$critical]['status'] ?? 'unknown'));
            if ($status === 'running') {
                continue;
            }
            $this->tryGuardedRestart($engine, $critical, $status);
        }
    }

    private function tryGuardedRestart(EngineApiService $engine, string $service, string $status): void
    {
        $base = "selfheal:{$service}:restart";
        $lastKey = "{$base}:last";
        $historyKey = "{$base}:history";

        $nowTs = now()->timestamp;
        $last = (int) (Cache::get($lastKey, 0));
        if ($last > 0 && ($nowTs - $last) < self::COOLDOWN_SECONDS) {
            $this->emitGuardrailAlert($service, 'cooldown', 'Auto-restart cooldown aktif, islem ertelendi.');

            return;
        }

        $history = Cache::get($historyKey, []);
        if (! is_array($history)) {
            $history = [];
        }
        $history = array_values(array_filter($history, static fn ($ts) => is_numeric($ts) && ((int) $ts) >= ($nowTs - self::WINDOW_SECONDS)));
        if (count($history) >= self::MAX_SAME_ACTION) {
            $this->emitGuardrailAlert($service, 'limit', 'Auto-restart limitine ulasildi (3/5dk), loop engellendi.');

            return;
        }

        $resp = $engine->controlService($service, 'restart');
        $ok = empty($resp['error']);

        $history[] = $nowTs;
        Cache::put($historyKey, $history, now()->addSeconds(self::WINDOW_SECONDS + 60));
        Cache::put($lastKey, $nowTs, now()->addSeconds(self::COOLDOWN_SECONDS + 60));

        $this->createAlert([
            'level' => $ok ? 'info' : 'error',
            'title' => $ok ? "Auto-restart uygulandi: {$service}" : "Auto-restart basarisiz: {$service}",
            'message' => $ok
                ? 'Servis running degildi, guvenli politika ile restart denendi.'
                : ((string) ($resp['error'] ?? 'bilinmeyen hata')),
            'path' => '/system',
            'dedupe_key' => "restart-{$service}-".date('YmdHi'),
        ]);
    }

    private function emitGuardrailAlert(string $service, string $reason, string $message): void
    {
        $dedupe = "guardrail-{$service}-{$reason}-".date('YmdHi');
        if ($this->alertExists($dedupe)) {
            return;
        }
        $this->createAlert([
            'level' => 'error',
            'title' => "Anti-loop korumasi: {$service}",
            'message' => $message,
            'path' => '/system',
            'dedupe_key' => $dedupe,
        ]);
    }

    private function alertExists(string $dedupeKey): bool
    {
        if (! Schema::hasTable('system_alerts')) {
            return false;
        }

        return SystemAlert::query()->where('dedupe_key', $dedupeKey)->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createAlert(array $payload): void
    {
        if (! Schema::hasTable('system_alerts')) {
            return;
        }
        SystemAlert::query()->create($payload);
    }
}
