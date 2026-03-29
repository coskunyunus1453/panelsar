<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

/**
 * Salt okunur sunucu / özellik durumu (gizli anahtar veya şifre dönmez).
 */
class ServerSetupController extends Controller
{
    public function capabilities(EngineApiService $engine): JsonResponse
    {
        $base = rtrim((string) config('panelsar.engine_url', ''), '/');
        $keySet = (string) config('panelsar.engine_internal_key', '') !== '';

        $health = null;
        $healthError = null;
        if ($base !== '') {
            try {
                $r = Http::timeout(4)->acceptJson()->get($base.'/health');
                $health = $r->successful() ? ($r->json() ?? ['ok' => true]) : ['http_status' => $r->status()];
            } catch (\Throwable $e) {
                $healthError = $e->getMessage();
            }
        }

        $stats = $engine->getSystemStats();
        $engineApiOk = $stats !== [] && (isset($stats['hostname']) || isset($stats['cpu_usage']));

        $apps = $engine->installerApps();
        $wpAutomated = false;
        foreach ($apps as $row) {
            if (is_array($row) && ($row['id'] ?? null) === 'wordpress' && ($row['automated'] ?? false) === true) {
                $wpAutomated = true;
                break;
            }
        }

        $mysql = config('panelsar.mysql_provision');
        $pg = config('panelsar.postgres_provision');
        $ui = config('panelsar.ui', []);

        return response()->json([
            'engine' => [
                'url_configured' => $base !== '',
                'url' => $base,
                'internal_key_set' => $keySet,
                'health' => $health,
                'health_error' => $healthError,
                'api_ok' => $engineApiOk,
                'stats_hostname' => $stats['hostname'] ?? null,
            ],
            'hosting_web_root' => (string) config('panelsar.hosting_web_root', ''),
            'mysql_provision' => [
                'enabled' => (bool) ($mysql['enabled'] ?? false),
                'host' => (string) ($mysql['host'] ?? ''),
                'port' => (int) ($mysql['port'] ?? 3306),
            ],
            'postgres_provision' => [
                'enabled' => (bool) ($pg['enabled'] ?? false),
                'host' => (string) ($pg['host'] ?? ''),
                'port' => (int) ($pg['port'] ?? 5432),
            ],
            'wordpress_installer' => [
                'panel_route_ready' => true,
                'engine_lists_wordpress' => $apps !== [],
                'engine_wordpress_automated' => $wpAutomated,
                'requires_mysql_db' => true,
                'ready' => $engineApiOk && $wpAutomated,
            ],
            'email' => [
                'mode' => 'mirror',
                'description_key' => 'server_setup.email_mirror_hint',
            ],
            'ui_links' => [
                'phpmyadmin_configured' => trim((string) ($ui['phpmyadmin_url'] ?? '')) !== '',
                'adminer_configured' => trim((string) ($ui['adminer_url'] ?? '')) !== '',
            ],
            'admin_system_page' => '/admin/system',
        ]);
    }
}
