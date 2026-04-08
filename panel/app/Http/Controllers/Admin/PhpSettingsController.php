<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncNginxUploadLimitJob;
use App\Models\PanelSetting;
use App\Services\EngineApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PhpSettingsController extends Controller
{
    public function __construct(
        private EngineApiService $engine,
    ) {}

    public function versions(): JsonResponse
    {
        return response()->json([
            'versions' => $this->engine->getPhpVersions(),
        ]);
    }

    public function ini(string $version): JsonResponse
    {
        $data = $this->engine->getPhpIni($version);
        $data['file_manager_limit_mb'] = $this->currentFileManagerLimitMb();

        return response()->json($data);
    }

    public function updateIni(Request $request, string $version): JsonResponse
    {
        $validated = $request->validate([
            'ini' => 'required|string|max:500000',
            'reload' => 'sometimes|boolean',
        ]);

        $payload = [
            'ini' => (string) $validated['ini'],
        ];
        if (array_key_exists('reload', $validated)) {
            $payload['reload'] = (bool) $validated['reload'];
        }

        $res = $this->engine->updatePhpIni($version, $payload);
        if (! empty($res['error'])) {
            return response()->json(['message' => $res['error']], 502);
        }

        $sync = $this->syncUploadLimitsFromIni((string) $validated['ini']);
        if ($sync !== null) {
            $res['limits_sync'] = $sync;
        }

        return response()->json($res);
    }

    public function modules(string $version): JsonResponse
    {
        $data = $this->engine->getPhpModules($version);

        return response()->json($data);
    }

    public function updateModules(Request $request, string $version): JsonResponse
    {
        $validated = $request->validate([
            'modules' => 'required|array|min:1',
            'modules.*.directive' => 'required|string|in:extension,zend_extension',
            'modules.*.name' => 'required|string|max:120',
            'modules.*.enabled' => 'required|boolean',
            'reload' => 'sometimes|boolean',
        ]);

        $payload = [
            'modules' => array_map(static fn ($m) => [
                'directive' => $m['directive'],
                'name' => $m['name'],
                'enabled' => (bool) $m['enabled'],
            ], $validated['modules']),
        ];

        if (array_key_exists('reload', $validated)) {
            $payload['reload'] = (bool) $validated['reload'];
        }

        $res = $this->engine->updatePhpModules($version, $payload);
        if (! empty($res['error'])) {
            return response()->json(['message' => $res['error']], 502);
        }

        return response()->json($res);
    }

    public function syncNginxUploadLimit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit_mb' => 'required|integer|min:1|max:4096',
            'scope' => 'sometimes|string|in:panel,main',
        ]);

        $scope = (string) ($validated['scope'] ?? 'panel');
        $limitMb = (int) $validated['limit_mb'];

        $runId = (string) Str::uuid();
        $cacheKey = 'admin:php:nginx-upload-sync:'.$runId;

        $steps = [
            ['key' => 'read_config', 'ok' => false, 'message' => 'Reading Nginx config'],
            ['key' => 'patch_config', 'ok' => false, 'message' => 'Updating client_max_body_size'],
            ['key' => 'test_reload', 'ok' => false, 'message' => 'Testing and reloading Nginx'],
        ];

        Cache::put($cacheKey, [
            'run_id' => $runId,
            'status' => 'queued',
            'progress' => 0,
            'steps' => $steps,
        ], now()->addMinutes(30));

        dispatch(new SyncNginxUploadLimitJob($runId, $limitMb, $scope))->afterResponse();

        return response()->json([
            'run_id' => $runId,
            'status' => 'queued',
        ], 202);
    }

    public function syncNginxUploadLimitStatus(string $runId): JsonResponse
    {
        $cacheKey = 'admin:php:nginx-upload-sync:'.$runId;
        $state = Cache::get($cacheKey);
        if (! is_array($state) || empty($state['status'])) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($state);
    }

    private function currentFileManagerLimitMb(): int
    {
        $override = (int) (PanelSetting::query()->where('key', 'limits.max_file_manager_size_mb')->value('value') ?? 0);
        if ($override > 0) {
            return $override;
        }

        return max(1, (int) config('hostvim.limits.max_file_manager_size_mb', 50));
    }

    /**
     * @return array<string, int|string>|null
     */
    private function syncUploadLimitsFromIni(string $ini): ?array
    {
        $uploadMb = $this->extractIniSizeMb($ini, 'upload_max_filesize');
        $postMb = $this->extractIniSizeMb($ini, 'post_max_size');
        if ($uploadMb === null && $postMb === null) {
            return null;
        }

        $current = $this->currentFileManagerLimitMb();
        $effectivePhpMb = max(1, min(
            $uploadMb ?? $current,
            $postMb ?? $current
        ));

        // multipart/form-data başlık payı için küçük bir tampon bırak.
        $recommendedMb = max(1, $effectivePhpMb - 8);

        PanelSetting::query()->updateOrCreate(
            ['key' => 'limits.max_file_manager_size_mb'],
            ['value' => (string) $recommendedMb]
        );

        return [
            'php_upload_max_filesize_mb' => $uploadMb ?? 0,
            'php_post_max_size_mb' => $postMb ?? 0,
            'effective_php_limit_mb' => $effectivePhpMb,
            'file_manager_limit_mb' => $recommendedMb,
            'nginx_hint_client_max_body_size_mb' => $effectivePhpMb,
            'message' => 'File manager limiti PHP ini değerlerine göre senkronlandı.',
        ];
    }

    private function extractIniSizeMb(string $ini, string $key): ?int
    {
        if (! preg_match('/^\s*'.preg_quote($key, '/').'\s*=\s*([0-9.]+\s*[KMG]?)\s*$/mi', $ini, $m)) {
            return null;
        }
        $raw = strtoupper(str_replace(' ', '', (string) $m[1]));
        if ($raw === '') {
            return null;
        }
        $unit = substr($raw, -1);
        $num = is_numeric($unit) ? (float) $raw : (float) substr($raw, 0, -1);
        if ($num <= 0) {
            return null;
        }
        $mb = match ($unit) {
            'G' => (int) ceil($num * 1024),
            'K' => (int) ceil($num / 1024),
            'M' => (int) ceil($num),
            default => (int) ceil($num / (1024 * 1024)),
        };

        return max(1, $mb);
    }
}

