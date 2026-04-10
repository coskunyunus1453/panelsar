<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\DeploymentConfig;
use App\Models\DeploymentRun;
use App\Models\Domain;
use App\Services\SafeAuditLogger;
use App\Services\AutoWebConfigurator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class DeploymentController extends Controller
{
    use AuthorizesUserDomain;

    public function show(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $cfg = $domain->deploymentConfig ?? DeploymentConfig::create([
            'domain_id' => $domain->id,
            'branch' => 'main',
            'runtime' => 'laravel',
            'webhook_token' => Str::random(48),
            'auto_deploy' => false,
        ]);

        return response()->json(['config' => $cfg]);
    }

    public function update(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $validated = $request->validate([
            'repo_url' => ['nullable', 'string', 'max:255', 'regex:/^(https:\/\/|git@)[A-Za-z0-9._:\/-]+(\.git)?$/'],
            'branch' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'branch_whitelist' => 'nullable|array|max:30',
            'branch_whitelist.*' => ['string', 'max:100', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'runtime' => 'nullable|string|in:laravel,node,php',
            'auto_deploy' => 'sometimes|boolean',
            'rotate_webhook_token' => 'sometimes|boolean',
        ]);

        $cfg = $domain->deploymentConfig ?? new DeploymentConfig(['domain_id' => $domain->id]);
        if (array_key_exists('repo_url', $validated)) {
            $cfg->repo_url = trim((string) $validated['repo_url']) ?: null;
        }
        if (array_key_exists('branch', $validated)) {
            $cfg->branch = trim((string) $validated['branch']) ?: 'main';
        }
        if (array_key_exists('branch_whitelist', $validated)) {
            $cfg->branch_whitelist = array_values(array_unique(array_filter(array_map(
                static fn ($v) => trim((string) $v),
                (array) $validated['branch_whitelist']
            ))));
        }
        if (array_key_exists('runtime', $validated)) {
            $cfg->runtime = $validated['runtime'];
        }
        if (array_key_exists('auto_deploy', $validated)) {
            $cfg->auto_deploy = (bool) $validated['auto_deploy'];
        }
        if (($validated['rotate_webhook_token'] ?? false) || empty($cfg->webhook_token)) {
            $cfg->webhook_token = Str::random(48);
        }
        $cfg->save();
        $this->audit($request, $domain, 'config_update', true, null);

        return response()->json([
            'message' => 'deployment config saved',
            'config' => $cfg,
        ]);
    }

    public function run(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $cfg = $domain->deploymentConfig;
        if (! $cfg) {
            return response()->json(['message' => 'deployment config not found'], 422);
        }
        $run = DeploymentRun::create([
            'domain_id' => $domain->id,
            'user_id' => $request->user()?->id,
            'trigger' => 'manual',
            'status' => 'queued',
        ]);
        Bus::dispatch(function () use ($domain, $cfg, $run): void {
            $this->executeDeploy($domain, $cfg, 'manual', $run->user_id, $run->id);
        })->afterResponse();
        $this->audit($request, $domain, 'run_manual_queued', true, null);

        return response()->json(['message' => 'deploy queued', 'run' => $run], 202);
    }

    public function runs(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $rows = DeploymentRun::query()
            ->where('domain_id', $domain->id)
            ->latest('id')
            ->limit(30)
            ->get();

        return response()->json(['runs' => $rows]);
    }

    public function webhook(Request $request, Domain $domain): JsonResponse
    {
        $cfg = $domain->deploymentConfig;
        if (! $cfg || ! $cfg->auto_deploy) {
            return response()->json(['message' => 'auto deploy disabled'], 422);
        }
        $branch = $this->extractWebhookBranch($request);
        if (! $this->isBranchAllowed($cfg, $branch)) {
            return response()->json(['message' => 'branch not allowed'], 422);
        }
        if (! $this->verifyWebhookSignature($request, (string) $cfg->webhook_token)) {
            return response()->json(['message' => 'invalid signature'], 403);
        }
        if (! $this->verifyWebhookReplayWindow($request, $domain)) {
            return response()->json(['message' => 'replay blocked'], 409);
        }
        $run = DeploymentRun::create([
            'domain_id' => $domain->id,
            'user_id' => null,
            'trigger' => 'webhook',
            'status' => 'queued',
        ]);
        Bus::dispatch(function () use ($domain, $cfg, $run): void {
            $this->executeDeploy($domain, $cfg, 'webhook', null, $run->id);
        })->afterResponse();
        SafeAuditLogger::info('hostvim.deploy_audit', [
            'action' => 'webhook_queued',
            'domain' => $domain->name,
            'run_id' => $run->id,
            'branch' => $branch,
        ], $request);

        return response()->json(['message' => 'webhook deploy queued', 'run' => $run], 202);
    }

    public function rollback(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $cfg = $domain->deploymentConfig;
        if (! $cfg) {
            return response()->json(['message' => 'deployment config not found'], 422);
        }
        $target = DeploymentRun::query()
            ->where('domain_id', $domain->id)
            ->where('status', 'success')
            ->whereNotNull('commit_hash')
            ->latest('id')
            ->first();
        if (! $target || ! $target->commit_hash) {
            return response()->json(['message' => 'no successful commit for rollback'], 422);
        }
        $run = DeploymentRun::create([
            'domain_id' => $domain->id,
            'user_id' => $request->user()?->id,
            'trigger' => 'rollback',
            'status' => 'queued',
        ]);
        Bus::dispatch(function () use ($domain, $cfg, $run, $target): void {
            $this->executeRollback($domain, $cfg, (string) $target->commit_hash, $run->id);
        })->afterResponse();
        $this->audit($request, $domain, 'rollback_queued', true, null);

        return response()->json(['message' => 'rollback queued', 'run' => $run], 202);
    }

    private function executeDeploy(Domain $domain, DeploymentConfig $cfg, string $trigger, ?int $userId, ?int $existingRunId = null): DeploymentRun
    {
        $docroot = (string) $domain->document_root;
        $output = [];

        $run = $existingRunId
            ? (DeploymentRun::find($existingRunId) ?? DeploymentRun::create([
                'domain_id' => $domain->id,
                'user_id' => $userId,
                'trigger' => $trigger,
                'status' => 'queued',
            ]))
            : DeploymentRun::create([
                'domain_id' => $domain->id,
                'user_id' => $userId,
                'trigger' => $trigger,
                'status' => 'queued',
            ]);
        $run->update(['status' => 'running', 'started_at' => now()]);

        $push = function (string $line) use (&$output): void {
            $output[] = trim($line);
        };

        try {
            $safeDocroot = $this->assertSafeDocroot($docroot);
            $docroot = $safeDocroot;
            if (! is_dir($docroot.'/.git')) {
                throw new \RuntimeException('git repository not found in document root');
            }

            $branch = trim((string) $cfg->branch) ?: 'main';
            $this->runCmd($docroot, sprintf('git fetch origin %s', escapeshellarg($branch)), $push);
            $this->runCmd($docroot, sprintf('git checkout %s', escapeshellarg($branch)), $push);
            $this->runCmd($docroot, sprintf('git pull --ff-only origin %s', escapeshellarg($branch)), $push);

            $runtime = strtolower((string) $cfg->runtime);
            if ($runtime === 'laravel') {
                $this->runCmd($docroot, 'composer install --no-interaction --prefer-dist --no-dev', $push);
                $this->runCmd($docroot, 'php artisan migrate --force', $push);
                $this->runCmd($docroot, 'php artisan optimize:clear', $push);
            } elseif ($runtime === 'node') {
                $this->runCmd($docroot, 'npm ci --no-audit --no-fund', $push);
                $this->runCmd($docroot, 'npm run build', $push);
            } elseif ($runtime === 'php') {
                $this->runCmd($docroot, 'composer install --no-interaction --prefer-dist --no-dev', $push);
            }

            // Güvenli otomatik web yapılandırma: deploy sonrası proje tipine göre docroot/perf güncelle.
            $auto = app(AutoWebConfigurator::class)->detectAndApply($domain->fresh());
            if (! ($auto['applied'] ?? false)) {
                $push('[warn] auto web config skipped: '.(string) ($auto['error'] ?? 'unknown'));
            } else {
                $push('[info] auto web config applied: profile='.(string) ($auto['profile'] ?? '').' variant='.(string) ($auto['variant'] ?? ''));
            }

            $commit = trim((string) shell_exec('cd '.escapeshellarg($docroot).' && git rev-parse --short HEAD 2>/dev/null'));
            $run->update([
                'status' => 'success',
                'commit_hash' => $commit !== '' ? $commit : null,
                'output' => implode("\n", array_filter($output)),
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $push('[error] '.$e->getMessage());
            $run->update([
                'status' => 'failed',
                'output' => implode("\n", array_filter($output)),
                'finished_at' => now(),
            ]);
        }

        return $run->fresh();
    }

    private function executeRollback(Domain $domain, DeploymentConfig $cfg, string $commit, int $runId): void
    {
        $run = DeploymentRun::find($runId);
        if (! $run) {
            return;
        }
        $output = [];
        $push = function (string $line) use (&$output): void {
            $output[] = trim($line);
        };
        $run->update(['status' => 'running', 'started_at' => now()]);
        try {
            $docroot = $this->assertSafeDocroot((string) $domain->document_root);
            $branch = trim((string) $cfg->branch) ?: 'main';
            $this->runCmd($docroot, sprintf('git checkout %s', escapeshellarg($branch)), $push);
            $this->runCmd($docroot, sprintf('git reset --hard %s', escapeshellarg($commit)), $push);
            $runtime = strtolower((string) $cfg->runtime);
            if ($runtime === 'laravel') {
                $this->runCmd($docroot, 'composer install --no-interaction --prefer-dist --no-dev', $push);
                $this->runCmd($docroot, 'php artisan optimize:clear', $push);
            } elseif ($runtime === 'node') {
                $this->runCmd($docroot, 'npm ci --no-audit --no-fund', $push);
                $this->runCmd($docroot, 'npm run build', $push);
            } else {
                $this->runCmd($docroot, 'composer install --no-interaction --prefer-dist --no-dev', $push);
            }
            $run->update([
                'status' => 'success',
                'commit_hash' => $commit,
                'output' => implode("\n", array_filter($output)),
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $push('[error] '.$e->getMessage());
            $run->update([
                'status' => 'failed',
                'output' => implode("\n", array_filter($output)),
                'finished_at' => now(),
            ]);
        }
    }

    private function runCmd(string $cwd, string $cmd, callable $push): void
    {
        $push('$ '.$cmd);
        $p = Process::fromShellCommandline($cmd, $cwd, null, null, 600);
        $p->run(function (string $type, string $buffer) use ($push): void {
            $lines = preg_split("/\r\n|\n|\r/", $buffer) ?: [];
            foreach ($lines as $ln) {
                if (trim($ln) !== '') {
                    $push($ln);
                }
            }
        });
        if (! $p->isSuccessful()) {
            throw new \RuntimeException($p->getErrorOutput() ?: $p->getOutput() ?: 'command failed');
        }
    }

    private function assertSafeDocroot(string $docroot): string
    {
        $realDocroot = realpath($docroot);
        if ($realDocroot === false || ! is_dir($realDocroot)) {
            throw new \RuntimeException('document root not found');
        }
        $hostingRoot = (string) config('hostvim.hosting_web_root', '');
        $realHostingRoot = $hostingRoot !== '' ? realpath($hostingRoot) : false;
        if ($realHostingRoot === false || ! is_dir($realHostingRoot)) {
            throw new \RuntimeException('hosting root invalid');
        }
        $prefix = rtrim($realHostingRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($realDocroot.DIRECTORY_SEPARATOR, $prefix)) {
            throw new \RuntimeException('document root escapes hosting root');
        }

        return $realDocroot;
    }

    private function audit(Request $request, Domain $domain, string $action, bool $success, ?string $error): void
    {
        // Security hardening: detailed deployment audit logs disabled.

    }

    private function extractWebhookBranch(Request $request): ?string
    {
        $ref = (string) data_get($request->json()->all(), 'ref', '');
        if ($ref === '') {
            return null;
        }
        if (str_starts_with($ref, 'refs/heads/')) {
            return substr($ref, strlen('refs/heads/'));
        }

        return $ref;
    }

    private function isBranchAllowed(DeploymentConfig $cfg, ?string $branch): bool
    {
        $allowed = array_values(array_filter(array_map(
            static fn ($v) => trim((string) $v),
            (array) ($cfg->branch_whitelist ?? [])
        )));
        if ($branch === null || $branch === '') {
            return true;
        }
        if ($allowed === []) {
            return true;
        }

        return in_array($branch, $allowed, true);
    }

    private function verifyWebhookSignature(Request $request, string $secret): bool
    {
        $raw = (string) $request->getContent();
        $gitHub = trim((string) $request->header('X-Hub-Signature-256', ''));
        if ($gitHub !== '') {
            $calc = 'sha256='.hash_hmac('sha256', $raw, $secret);

            return hash_equals($calc, $gitHub);
        }
        $gitLabToken = trim((string) $request->header('X-Gitlab-Token', ''));
        if ($gitLabToken !== '') {
            return hash_equals($secret, $gitLabToken);
        }
        $token = trim((string) $request->header('X-Deploy-Token'));

        return $token !== '' && hash_equals($secret, $token);
    }

    private function verifyWebhookReplayWindow(Request $request, Domain $domain): bool
    {
        $deliveryId = trim((string) (
            $request->header('X-GitHub-Delivery')
            ?: $request->header('X-Gitlab-Event-UUID')
            ?: $request->header('X-Request-Id')
        ));
        if ($deliveryId === '') {
            // Delivery ID yoksa yine de payload hash ile kısa replay engeli uygula.
            $deliveryId = 'body:'.hash('sha256', (string) $request->getContent());
        }
        $key = 'deploy:webhook:dedupe:'.$domain->id.':'.$deliveryId;
        if (Cache::has($key)) {
            SafeAuditLogger::warning('hostvim.deploy_audit', [
                'action' => 'webhook_replay_blocked',
                'domain' => $domain->name,
                'delivery_fp' => substr(hash('sha256', $deliveryId), 0, 16),
            ], $request);

            return false;
        }
        Cache::put($key, 1, now()->addMinutes(15));

        return true;
    }
}
