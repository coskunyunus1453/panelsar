<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileManagerController extends Controller
{
    use AuthorizesUserDomain;

    public function __construct(
        private EngineApiService $engine,
    ) {}

    public function index(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $path = (string) $request->query('path', '');

        $list = $this->engine->listFilesResult($domain->name, $path);
        if ($list['error'] !== null) {
            return response()->json([
                'message' => $list['error'],
                'entries' => [],
                'document_root_hint' => $domain->document_root,
            ], 503);
        }

        return response()->json([
            'entries' => $list['entries'],
            'document_root_hint' => $domain->document_root,
        ]);
    }

    public function search(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $validated = $request->validate([
            'path' => 'nullable|string|max:2048',
            'q' => 'required|string|min:2|max:256',
        ]);

        return response()->json([
            'hits' => $this->engine->searchFiles(
                $domain->name,
                (string) ($validated['path'] ?? ''),
                $validated['q']
            ),
        ]);
    }

    public function mkdir(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $validated = $request->validate(['path' => 'required|string']);

        $result = $this->engine->mkdirFile($domain->name, $validated['path']);
        if (! empty($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json($result);
    }

    public function destroy(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $validated = $request->validate(['path' => 'required|string']);

        $result = $this->engine->deleteFile($domain->name, $validated['path']);
        if (! empty($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json($result);
    }

    public function read(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $validated = $request->validate(['path' => 'required|string']);

        return response()->json([
            'content' => $this->engine->readFile($domain->name, $validated['path']),
        ]);
    }

    public function write(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $validated = $request->validate([
            'path' => 'required|string',
            'content' => 'nullable|string',
        ]);

        $result = $this->engine->writeFile($domain->name, $validated['path'], $validated['content'] ?? '');
        if (! empty($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json($result);
    }

    public function upload(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $maxKb = max(1, (int) config('panelsar.limits.max_file_manager_size_mb', 50)) * 1024;
        $validated = $request->validate([
            'path' => 'nullable|string',
            'file' => 'required|file|max:'.$maxKb,
        ]);
        $relPath = (string) ($validated['path'] ?? '');
        $result = $this->engine->uploadFile($domain->name, $relPath, $request->file('file'));

        $status = isset($result['error']) ? 502 : 200;

        return response()->json($result, $status);
    }
}
