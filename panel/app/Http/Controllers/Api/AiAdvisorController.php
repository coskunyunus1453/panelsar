<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\CronJob;
use App\Models\DeploymentRun;
use App\Models\Domain;
use App\Models\User;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class AiAdvisorController extends Controller
{
    use AuthorizesUserDomain;

    public function __construct(
        private EngineApiService $engine,
    ) {}

    public function fileEditor(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $validated = $request->validate([
            'path' => 'required|string|max:2048',
            'content' => 'nullable|string',
        ]);
        $path = strtolower((string) $validated['path']);
        $content = (string) ($validated['content'] ?? '');
        $issues = [];
        $syntaxOk = true;

        if (str_ends_with($path, '.php')) {
            $tmp = tempnam(sys_get_temp_dir(), 'psr-ai-');
            file_put_contents($tmp, $content);
            $p = new Process(['php', '-l', $tmp]);
            $p->run();
            @unlink($tmp);
            if (! $p->isSuccessful()) {
                $syntaxOk = false;
                $issues[] = trim($p->getErrorOutput() ?: $p->getOutput() ?: 'php syntax error');
            }
        } elseif (str_ends_with($path, '.json')) {
            json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $syntaxOk = false;
                $issues[] = 'JSON parse error: '.json_last_error_msg();
            }
        }

        $len = mb_strlen($content);
        $lineCount = substr_count($content, "\n") + 1;
        $autoSaveSec = 30;
        if ($len > 50_000 || $lineCount > 800) {
            $autoSaveSec = 45;
        } elseif ($len < 5_000 && $lineCount < 120) {
            $autoSaveSec = 20;
        }

        return response()->json([
            'syntax_ok' => $syntaxOk,
            'issues' => $issues,
            'suggestions' => [
                sprintf('Auto-save araligi: %d sn', $autoSaveSec),
                'Kaydetmeden once degisiklikleri diff ile kontrol et.',
            ],
            'auto_save_seconds' => $autoSaveSec,
        ]);
    }

    public function deploy(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $runs = DeploymentRun::query()
            ->where('domain_id', $domain->id)
            ->latest('id')
            ->limit(12)
            ->get();
        $failed = $runs->where('status', 'failed')->count();
        $total = $runs->count();
        $successRate = $total > 0 ? round((($total - $failed) / $total) * 100, 1) : 100.0;
        $suggestions = [];
        if ($failed >= 3) {
            $suggestions[] = 'Son deploylarda hata yogun. Deploy adimlarini bol (pull/build/migrate) ve her adimi ayri logla.';
        }
        if ($successRate < 80) {
            $suggestions[] = 'Blue-green veya canary benzeri asamali rollout dusun.';
        }
        $suggestions[] = 'Webhook tokenini periyodik dondur ve branch whitelist uygula.';

        return response()->json([
            'success_rate' => $successRate,
            'failed_runs' => $failed,
            'total_runs' => $total,
            'suggestions' => $suggestions,
        ]);
    }

    public function cronBackup(Request $request): JsonResponse
    {
        $user = $request->user();
        $cronCount = CronJob::query()->where('user_id', $user->id)->count();
        $scheduleCount = BackupSchedule::query()->where('user_id', $user->id)->where('enabled', true)->count();
        $lastBackup = Backup::query()->where('user_id', $user->id)->latest('created_at')->first();
        $suggestions = [];
        if ($cronCount === 0) {
            $suggestions[] = 'Kritik bakim komutlari icin en az 1 cron gorevi tanimla.';
        }
        if ($scheduleCount === 0) {
            $suggestions[] = 'En az gunluk bir backup plani olustur (onerilen: 0 3 * * *).';
        }
        if (! $lastBackup || $lastBackup->created_at->lt(now()->subDays(3))) {
            $suggestions[] = 'Son backup eski. Geri yukleme testini haftalik calistir.';
        } else {
            $suggestions[] = 'Aylik otomatik restore testi ile backup dogrulugu arttir.';
        }

        return response()->json([
            'cron_jobs' => $cronCount,
            'backup_schedules' => $scheduleCount,
            'last_backup_at' => $lastBackup?->created_at,
            'suggestions' => $suggestions,
        ]);
    }

    public function monitoring(Request $request): JsonResponse
    {
        $stats = $this->engine->getSystemStats();
        $security = $this->engine->securityOverview();
        $alerts = [];
        $cpu = (float) ($stats['cpu']['usage'] ?? 0);
        $mem = (float) ($stats['memory']['usage_percent'] ?? 0);
        if ($cpu > 85) {
            $alerts[] = 'Yuksek CPU kullanimi tespit edildi.';
        }
        if ($mem > 90) {
            $alerts[] = 'Yuksek RAM kullanimi tespit edildi.';
        }
        if (($security['fail2ban']['enabled'] ?? false) !== true) {
            $alerts[] = 'Fail2ban kapali. Brute-force riskine acik.';
        }
        if (($security['modsecurity']['enabled'] ?? false) !== true) {
            $alerts[] = 'ModSecurity kapali. WAF korumasi devre disi.';
        }
        if (count($alerts) === 0) {
            $alerts[] = 'Anormallik tespit edilmedi. Normal sinirlarda.';
        }

        return response()->json([
            'alerts' => $alerts,
            'cpu_usage' => $cpu,
            'memory_usage' => $mem,
        ]);
    }

    public function access(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isAdmin()) {
            return response()->json([
                'alerts' => ['Detayli risk analizi sadece admin kullanicilar icin acik.'],
            ]);
        }
        $admins = User::query()->role('admin')->count();
        $usersWithout2fa = User::query()->where('two_factor_enabled', false)->count();
        $alerts = [];
        if ($admins > 3) {
            $alerts[] = 'Admin kullanici sayisi yuksek. Least-privilege modeli onerilir.';
        }
        if ($usersWithout2fa > 0) {
            $alerts[] = sprintf('%d kullanicida 2FA kapali. Kritik rollerde 2FA zorunlu yapin.', $usersWithout2fa);
        }
        if (count($alerts) === 0) {
            $alerts[] = 'Yetki modeli dengeli gorunuyor.';
        }

        return response()->json([
            'admin_count' => $admins,
            'twofa_disabled_users' => $usersWithout2fa,
            'alerts' => $alerts,
            'suggested_model' => 'RBAC + least privilege + 2FA + periodik rol denetimi',
        ]);
    }
}
