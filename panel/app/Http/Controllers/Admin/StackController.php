<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunStackInstallJob;
use App\Models\StackInstallRun;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StackController extends Controller
{
    public function modules(EngineApiService $engine): JsonResponse
    {
        return response()->json(['modules' => $engine->getStackModules()]);
    }

    public function install(Request $request, EngineApiService $engine): JsonResponse
    {
        $validated = $request->validate([
            'bundle_id' => 'required|string|max:120',
        ]);
        $run = StackInstallRun::query()->create([
            'user_id' => (int) $request->user()->id,
            'bundle_id' => $validated['bundle_id'],
            'status' => 'queued',
            'progress' => 0,
            'cancel_requested' => false,
            'message' => 'Kurulum kuyruğa alındı',
        ]);

        $isSyncQueue = (string) config('queue.default', 'sync') === 'sync';
        if ($isSyncQueue) {
            (new RunStackInstallJob($run->id, $validated['bundle_id']))->handle($engine);
            return response()->json([
                'message' => 'Kurulum tamamlandı',
                'run_id' => $run->id,
                'background' => false,
            ]);
        }

        RunStackInstallJob::dispatch($run->id, $validated['bundle_id'])->afterResponse();
        return response()->json([
            'message' => 'Kurulum arka planda başlatıldı',
            'run_id' => $run->id,
            'background' => true,
        ], 202);
    }

    public function runs(Request $request): JsonResponse
    {
        $rows = StackInstallRun::query()
            ->where('user_id', (int) $request->user()->id)
            ->latest('id')
            ->limit(20)
            ->get(['id', 'bundle_id', 'status', 'progress', 'cancel_requested', 'message', 'created_at', 'started_at', 'finished_at']);
        return response()->json(['runs' => $rows]);
    }

    public function showRun(Request $request, StackInstallRun $stackInstallRun): JsonResponse
    {
        if ((int) $stackInstallRun->user_id !== (int) $request->user()->id) {
            abort(403);
        }
        return response()->json(['run' => $stackInstallRun]);
    }

    public function cancelRun(Request $request, StackInstallRun $stackInstallRun): JsonResponse
    {
        if ((int) $stackInstallRun->user_id !== (int) $request->user()->id) {
            abort(403);
        }
        if (in_array($stackInstallRun->status, ['success', 'failed', 'cancelled'], true)) {
            return response()->json(['message' => 'Bu işlem zaten tamamlandı.'], 422);
        }

        if ($stackInstallRun->status === 'queued') {
            $stackInstallRun->status = 'cancelled';
            $stackInstallRun->message = 'Kurulum iptal edildi (kuyrukta).';
            $stackInstallRun->progress = 0;
            $stackInstallRun->finished_at = now();
            $stackInstallRun->cancel_requested = true;
            $stackInstallRun->save();
            return response()->json(['message' => 'Kurulum kuyruğu iptal edildi.']);
        }

        $stackInstallRun->cancel_requested = true;
        $stackInstallRun->message = 'İptal talebi alındı. İşlem mevcut adımı bitirince duracaktır.';
        $stackInstallRun->save();
        return response()->json(['message' => 'İptal talebi alındı.']);
    }
}
