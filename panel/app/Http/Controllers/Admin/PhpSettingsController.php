<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PanelSetting;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $desired = "client_max_body_size {$limitMb}m;";

        $cfg = $this->engine->getNginxConfig($scope);
        if (! empty($cfg['error'])) {
            return response()->json(['message' => (string) $cfg['error']], 502);
        }
        $content = (string) ($cfg['content'] ?? '');
        if (trim($content) === '') {
            return response()->json(['message' => 'Nginx config content not found.'], 422);
        }

        if (preg_match('/client_max_body_size\s+\S+;/i', $content) === 1) {
            $next = (string) preg_replace('/client_max_body_size\s+\S+;/i', $desired, $content);
        } elseif (preg_match('/server_name\s+[^;\n]+;\s*/i', $content) === 1) {
            $next = (string) preg_replace('/(server_name\s+[^;\n]+;\s*\n)/i', "$1    {$desired}\n", $content, 1);
        } else {
            $next = $content."\n    ".$desired."\n";
        }

        $updated = $this->engine->updateNginxConfig($scope, $next, true);
        if (! empty($updated['error'])) {
            return response()->json(['message' => (string) $updated['error']], 502);
        }

        return response()->json([
            'message' => 'Nginx upload limiti güncellendi.',
            'scope' => $scope,
            'client_max_body_size_mb' => $limitMb,
        ]);
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

