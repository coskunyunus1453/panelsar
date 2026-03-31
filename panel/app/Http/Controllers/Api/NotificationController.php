<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\CronJobRun;
use App\Models\DeploymentRun;
use App\Models\InstallerRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function feed(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $items = [];

        foreach (InstallerRun::query()->where('user_id', $userId)->latest('id')->limit(15)->get() as $r) {
            $items[] = [
                'id' => 'installer-'.$r->id,
                'level' => $r->status === 'failed' ? 'error' : ($r->status === 'success' ? 'success' : 'info'),
                'title' => 'Installer: '.strtoupper($r->app),
                'message' => $r->message,
                'path' => '/installer',
                'created_at' => optional($r->created_at)->toIso8601String(),
            ];
        }

        foreach (DeploymentRun::query()->where('user_id', $userId)->latest('id')->limit(15)->get() as $r) {
            $items[] = [
                'id' => 'deploy-'.$r->id,
                'level' => $r->status === 'failed' ? 'error' : ($r->status === 'success' ? 'success' : 'info'),
                'title' => 'Deploy: '.$r->trigger,
                'message' => $r->commit_hash ? ('commit: '.$r->commit_hash) : $r->status,
                'path' => '/deploy',
                'created_at' => optional($r->created_at)->toIso8601String(),
            ];
        }

        foreach (Backup::query()->where('user_id', $userId)->latest('id')->limit(15)->get() as $r) {
            $items[] = [
                'id' => 'backup-'.$r->id,
                'level' => $r->status === 'failed' ? 'error' : ($r->status === 'completed' ? 'success' : 'info'),
                'title' => 'Backup: '.($r->type ?: 'full'),
                'message' => $r->status,
                'path' => '/backups',
                'created_at' => optional($r->created_at)->toIso8601String(),
            ];
        }

        foreach (CronJobRun::query()->where('user_id', $userId)->latest('id')->limit(15)->get() as $r) {
            $items[] = [
                'id' => 'cron-'.$r->id,
                'level' => $r->status === 'failed' ? 'error' : 'info',
                'title' => 'Cron run #'.$r->id,
                'message' => $r->status.($r->exit_code !== null ? (' (exit '.$r->exit_code.')') : ''),
                'path' => '/cron',
                'created_at' => optional($r->created_at)->toIso8601String(),
            ];
        }

        usort($items, fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return response()->json(['items' => array_slice($items, 0, 50)]);
    }
}
