<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\CronJobRun;
use App\Models\DeploymentRun;
use App\Models\Domain;
use App\Models\InstallerRun;
use App\Models\SslCertificate;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MonitoringController extends Controller
{
    public function __construct(
        private EngineApiService $engine,
    ) {}

    public function userSummary(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'domains' => $user->domains()->count(),
            'databases' => $user->databases()->count(),
            'email_accounts' => $user->emailAccounts()->count(),
            'disk_estimate_mb' => $user->databases()->sum('size_mb'),
        ]);
    }

    public function server(Request $request): JsonResponse
    {
        return response()->json([
            'stats' => $this->engine->getSystemStats(),
            'services' => $this->engine->getServices(),
        ]);
    }

    public function health(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'domain_id' => ['nullable', 'integer', 'exists:domains,id'],
        ]);

        $selectedDomain = null;
        if (! empty($validated['domain_id'])) {
            $domainQ = Domain::query()->where('id', (int) $validated['domain_id']);
            if (! $user->isAdmin()) {
                $domainQ->where('user_id', (int) $user->id);
            }
            $selectedDomain = $domainQ->first();
            if (! $selectedDomain) {
                abort(403);
            }
        }

        $scope = $user->isAdmin() ? 'admin' : 'u'.$user->id;
        $scope .= $selectedDomain ? ':d'.$selectedDomain->id : ':all';
        $cacheKey = 'monitoring:health:'.$scope;

        $payload = Cache::remember($cacheKey, 20, function () use ($user, $selectedDomain): array {
            $t0 = microtime(true);
            $stats = $this->engine->getSystemStats();
            $services = $this->engine->getServices();
            $responseMs = (int) round((microtime(true) - $t0) * 1000);
            $siteResponseMs = $selectedDomain ? $this->probeDomainResponseMs((string) $selectedDomain->name) : null;

            $cpu = (float) ($stats['cpu_usage'] ?? 0);
            $ram = (float) ($stats['memory_percent'] ?? 0);
            $disk = (float) ($stats['disk_percent'] ?? 0);

            $score = 100.0;
            $reasons = [];

            // CPU (20)
            $cpuPenalty = max(0.0, min(20.0, ($cpu - 60.0) * 0.5));
            $score -= $cpuPenalty;
            $reasons[] = [
                'key' => 'cpu',
                'ok' => $cpuPenalty < 4,
                'label' => $cpuPenalty < 4 ? 'CPU normal' : 'CPU yuksek',
                'detail' => sprintf('CPU: %.1f%%', $cpu),
            ];

            // RAM (20)
            $ramPenalty = max(0.0, min(20.0, ($ram - 65.0) * 0.57));
            $score -= $ramPenalty;
            $reasons[] = [
                'key' => 'ram',
                'ok' => $ramPenalty < 4,
                'label' => $ramPenalty < 4 ? 'RAM normal' : 'RAM yuksek',
                'detail' => sprintf('RAM: %.1f%%', $ram),
            ];

            // Disk (15)
            $diskPenalty = max(0.0, min(15.0, ($disk - 70.0) * 0.5));
            $score -= $diskPenalty;
            $reasons[] = [
                'key' => 'disk',
                'ok' => $diskPenalty < 3,
                'label' => $diskPenalty < 3 ? 'Disk normal' : 'Disk doluluk yuksek',
                'detail' => sprintf('Disk: %.1f%%', $disk),
            ];

            // Response time (15)
            $rtBase = $siteResponseMs ?? $responseMs;
            $rtPenalty = max(0.0, min(15.0, ($rtBase - 250.0) / 35.0));
            $score -= $rtPenalty;
            $reasons[] = [
                'key' => 'rt',
                'ok' => $rtPenalty < 3,
                'label' => $rtPenalty < 3 ? 'Response iyi' : 'Response yavas',
                'detail' => $selectedDomain
                    ? ("Site: {$selectedDomain->name} ~ ".($siteResponseMs ?? 0).' ms')
                    : ("Engine API: {$responseMs} ms"),
            ];

            // Error rate (20): son 20 run
            if ($selectedDomain) {
                $inst = InstallerRun::query()->where('domain_id', (int) $selectedDomain->id)->latest('id')->limit(20)->get(['status']);
                $dep = DeploymentRun::query()->where('domain_id', (int) $selectedDomain->id)->latest('id')->limit(20)->get(['status']);
                $bak = Backup::query()->where('domain_id', (int) $selectedDomain->id)->latest('id')->limit(20)->get(['status']);
                $cron = collect();
            } elseif ($user->isAdmin()) {
                $inst = InstallerRun::query()->latest('id')->limit(20)->get(['status']);
                $dep = DeploymentRun::query()->latest('id')->limit(20)->get(['status']);
                $bak = Backup::query()->latest('id')->limit(20)->get(['status']);
                $cron = CronJobRun::query()->latest('id')->limit(20)->get(['status']);
            } else {
                $uid = (int) $user->id;
                $inst = InstallerRun::query()->where('user_id', $uid)->latest('id')->limit(20)->get(['status']);
                $dep = DeploymentRun::query()->where('user_id', $uid)->latest('id')->limit(20)->get(['status']);
                $bak = Backup::query()->where('user_id', $uid)->latest('id')->limit(20)->get(['status']);
                $cron = CronJobRun::query()->where('user_id', $uid)->latest('id')->limit(20)->get(['status']);
            }
            $statuses = $inst->pluck('status')->concat($dep->pluck('status'))->concat($bak->pluck('status'))->concat($cron->pluck('status'));
            $totalRuns = $statuses->count();
            $failed = $statuses->filter(fn ($s) => in_array(strtolower((string) $s), ['failed', 'error'], true))->count();
            $errRate = $totalRuns > 0 ? ($failed / $totalRuns) * 100.0 : 0.0;
            $errPenalty = min(20.0, $errRate * 0.5);
            $score -= $errPenalty;
            $reasons[] = [
                'key' => 'errors',
                'ok' => $errPenalty < 4,
                'label' => $errPenalty < 4 ? 'Error rate normal' : 'Error rate yuksek',
                'detail' => $totalRuns > 0 ? sprintf('Fail: %d/%d (%.1f%%)', $failed, $totalRuns, $errRate) : 'Son run verisi yok',
            ];

            // SSL (10)
            if ($selectedDomain) {
                $sslTotal = SslCertificate::query()->where('domain_id', (int) $selectedDomain->id)->count();
                $sslBad = SslCertificate::query()->where('domain_id', (int) $selectedDomain->id)
                    ->where(function ($q) {
                        $q->where('status', '!=', 'active')
                            ->orWhereNotNull('expires_at')->where('expires_at', '<', now()->addDays(7));
                    })->count();
            } elseif ($user->isAdmin()) {
                $sslTotal = SslCertificate::query()->count();
                $sslBad = SslCertificate::query()
                    ->where(function ($q) {
                        $q->where('status', '!=', 'active')
                            ->orWhereNotNull('expires_at')->where('expires_at', '<', now()->addDays(7));
                    })->count();
            } else {
                $sslTotal = SslCertificate::query()->whereHas('domain', fn ($q) => $q->where('user_id', (int) $user->id))->count();
                $sslBad = SslCertificate::query()->whereHas('domain', fn ($q) => $q->where('user_id', (int) $user->id))
                    ->where(function ($q) {
                        $q->where('status', '!=', 'active')
                            ->orWhereNotNull('expires_at')->where('expires_at', '<', now()->addDays(7));
                    })->count();
            }
            $sslPenalty = $sslTotal > 0 ? min(10.0, ($sslBad / max(1, $sslTotal)) * 10.0) : 0.0;
            $score -= $sslPenalty;
            $reasons[] = [
                'key' => 'ssl',
                'ok' => $sslPenalty < 2,
                'label' => $sslPenalty < 2 ? 'SSL OK' : 'SSL riski var',
                'detail' => $sslTotal > 0 ? sprintf('Problemli SSL: %d/%d', $sslBad, $sslTotal) : 'SSL kaydi yok',
            ];

            // Service availability bonus/penalty
            $serviceBy = collect($services)->keyBy(fn ($s) => strtolower((string) ($s['name'] ?? '')));
            foreach (['nginx', 'apache2'] as $svcName) {
                if ($serviceBy->has($svcName) && strtolower((string) ($serviceBy[$svcName]['status'] ?? '')) !== 'running') {
                    $score -= 8.0;
                    $reasons[] = [
                        'key' => 'svc_'.$svcName,
                        'ok' => false,
                        'label' => strtoupper($svcName).' servis sorunu',
                        'detail' => 'Servis running degil',
                    ];
                }
            }

            $score = max(0, min(100, (int) round($score)));
            $grade = $score >= 90 ? 'excellent' : ($score >= 75 ? 'good' : ($score >= 60 ? 'warning' : 'critical'));

            return [
                'score' => $score,
                'grade' => $grade,
                'response_ms' => $responseMs,
                'site_response_ms' => $siteResponseMs,
                'scope' => $selectedDomain ? 'domain' : 'global',
                'domain' => $selectedDomain ? [
                    'id' => (int) $selectedDomain->id,
                    'name' => (string) $selectedDomain->name,
                    'status' => (string) ($selectedDomain->status ?? 'unknown'),
                ] : null,
                'snapshot' => [
                    'cpu' => round($cpu, 1),
                    'ram' => round($ram, 1),
                    'disk' => round($disk, 1),
                    'error_rate' => round($errRate, 1),
                ],
                'reasons' => array_slice($reasons, 0, 8),
            ];
        });

        return response()->json($payload);
    }

    public function healthSites(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $limit = (int) ($validated['limit'] ?? 20);

        $baseQ = Domain::query()->select(['id', 'name', 'status', 'user_id'])->orderBy('id', 'desc');
        if (! $user->isAdmin()) {
            $baseQ->where('user_id', (int) $user->id);
        }
        $domains = $baseQ->limit($limit)->get();

        $items = $domains->map(function (Domain $d) {
            $score = 100.0;
            $reasons = [];

            if (strtolower((string) $d->status) !== 'active') {
                $score -= 30;
                $reasons[] = 'Domain aktif degil';
            }

            $ssl = SslCertificate::query()->where('domain_id', (int) $d->id)->latest('id')->first();
            if (! $ssl) {
                $score -= 10;
                $reasons[] = 'SSL kaydi yok';
            } elseif ((string) $ssl->status !== 'active') {
                $score -= 20;
                $reasons[] = 'SSL aktif degil';
            } elseif ($ssl->expires_at !== null && $ssl->expires_at->lt(now()->addDays(7))) {
                $score -= 15;
                $reasons[] = 'SSL yakinda bitiyor';
            }

            $runs = InstallerRun::query()->where('domain_id', (int) $d->id)->latest('id')->limit(8)->pluck('status')
                ->concat(DeploymentRun::query()->where('domain_id', (int) $d->id)->latest('id')->limit(8)->pluck('status'))
                ->concat(Backup::query()->where('domain_id', (int) $d->id)->latest('id')->limit(8)->pluck('status'));

            $total = $runs->count();
            $failed = $runs->filter(fn ($s) => in_array(strtolower((string) $s), ['failed', 'error'], true))->count();
            if ($total > 0) {
                $errRate = ($failed / $total) * 100.0;
                $pen = min(30.0, $errRate * 0.5);
                $score -= $pen;
                if ($pen >= 6) {
                    $reasons[] = sprintf('Islem hatasi: %d/%d', $failed, $total);
                }
            }

            $score = max(0, min(100, (int) round($score)));
            $grade = $score >= 90 ? 'excellent' : ($score >= 75 ? 'good' : ($score >= 60 ? 'warning' : 'critical'));

            return [
                'domain_id' => (int) $d->id,
                'name' => (string) $d->name,
                'score' => $score,
                'grade' => $grade,
                'reasons' => array_slice($reasons, 0, 3),
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'limit' => $limit,
        ]);
    }

    private function probeDomainResponseMs(string $domain): ?int
    {
        $domain = trim($domain);
        if ($domain === '') {
            return null;
        }
        foreach ([443, 80] as $port) {
            $errno = 0;
            $errstr = '';
            $t0 = microtime(true);
            $sock = @fsockopen($domain, $port, $errno, $errstr, 1.5);
            $ms = (int) round((microtime(true) - $t0) * 1000);
            if (is_resource($sock)) {
                fclose($sock);
                return $ms;
            }
        }

        return null;
    }
}
