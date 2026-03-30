<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        $limit = (int) $request->query('limit', 200);
        $offset = (int) $request->query('offset', 0);
        $sort = (string) $request->query('sort', 'name');
        $order = (string) $request->query('order', 'asc');

        $limit = max(1, min(5000, $limit));
        $offset = max(0, $offset);
        $sort = in_array($sort, ['name', 'size', 'mtime'], true) ? $sort : 'name';
        $order = strtolower($order) === 'desc' ? 'desc' : 'asc';

        $list = $this->engine->listFilesResult($domain->name, $path, $limit, $offset, $sort, $order);
        if ($list['error'] !== null) {
            return response()->json([
                'message' => $list['error'],
                'entries' => [],
                'document_root_hint' => $domain->document_root,
                'total' => 0,
                'offset' => $offset,
                'limit' => $limit,
            ], 503);
        }

        return response()->json([
            'entries' => $list['entries'],
            'document_root_hint' => $domain->document_root,
            'total' => $list['total'] ?? 0,
            'offset' => $list['offset'] ?? $offset,
            'limit' => $list['limit'] ?? $limit,
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
        $from = $this->resolveFileManagerPath($request);
        if ($from === '') {
            return response()->json(['message' => 'The path field is required.'], 422);
        }
        try {
            $result = $this->engine->deleteFile($domain->name, $from);
            if (! empty($result['error'])) {
                $this->logFileAction($request, $domain, 'delete', $from, null, false, $result['error']);
                return response()->json(['message' => $result['error']], 422);
            }
            $this->logFileAction($request, $domain, 'delete', $from, null, true, null);
        } catch (\Throwable $e) {
            $this->logFileAction($request, $domain, 'delete', $from, null, false, $e->getMessage());
            throw $e;
        }

        return response()->json($result);
    }

    public function read(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $path = $this->resolveFileManagerPath($request);
        if ($path === '') {
            return response()->json(['message' => 'The path field is required.'], 422);
        }

        return response()->json([
            'content' => $this->engine->readFile($domain->name, $path),
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

        $from = $validated['path'];
        try {
            $result = $this->engine->writeFile($domain->name, $from, $validated['content'] ?? '');
            if (! empty($result['error'])) {
                $this->logFileAction($request, $domain, 'edit', $from, null, false, $result['error']);
                return response()->json(['message' => $result['error']], 422);
            }
            $this->logFileAction($request, $domain, 'edit', $from, null, true, null);
        } catch (\Throwable $e) {
            $this->logFileAction($request, $domain, 'edit', $from, null, false, $e->getMessage());
            throw $e;
        }

        return response()->json($result);
    }

    public function create(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $validated = $request->validate([
            'path' => 'required|string',
            'content' => 'nullable|string',
        ]);

        $from = $validated['path'];
        try {
            $result = $this->engine->createFile($domain->name, $from, $validated['content'] ?? '');
            if (! empty($result['error'])) {
                $this->logFileAction($request, $domain, 'create', $from, null, false, $result['error']);
                return response()->json(['message' => $result['error']], 422);
            }

            $this->logFileAction($request, $domain, 'create', $from, null, true, null);

            return response()->json($result);
        } catch (\Throwable $e) {
            $this->logFileAction($request, $domain, 'create', $from, null, false, $e->getMessage());
            throw $e;
        }
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
        try {
            $result = $this->engine->uploadFile($domain->name, $relPath, $request->file('file'));
            $ok = empty($result['error']);
            $this->logFileAction($request, $domain, 'upload', $relPath, null, $ok, $result['error'] ?? null);

            $status = $ok ? 200 : 502;
            return response()->json($result, $status);
        } catch (\Throwable $e) {
            $this->logFileAction($request, $domain, 'upload', $relPath, null, false, $e->getMessage());
            throw $e;
        }
    }

    public function rename(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $validated = $request->validate([
            'from' => 'required|string',
            'to' => 'required|string',
        ]);

        $from = $validated['from'];
        $to = $validated['to'];
        try {
            $result = $this->engine->renameFile($domain->name, $from, $to);
            if (! empty($result['error'])) {
                $this->logFileAction($request, $domain, 'rename', $from, $to, false, $result['error']);
                return response()->json(['message' => $result['error']], 422);
            }
            $this->logFileAction($request, $domain, 'rename', $from, $to, true, null);

            return response()->json($result);
        } catch (\Throwable $e) {
            $this->logFileAction($request, $domain, 'rename', $from, $to, false, $e->getMessage());
            throw $e;
        }
    }

    public function move(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $validated = $request->validate([
            'from' => 'required|string',
            'to' => 'required|string',
        ]);

        $from = $validated['from'];
        $to = $validated['to'];
        try {
            $result = $this->engine->moveFile($domain->name, $from, $to);
            if (! empty($result['error'])) {
                $this->logFileAction($request, $domain, 'move', $from, $to, false, $result['error']);
                return response()->json(['message' => $result['error']], 422);
            }
            $this->logFileAction($request, $domain, 'move', $from, $to, true, null);

            return response()->json($result);
        } catch (\Throwable $e) {
            $this->logFileAction($request, $domain, 'move', $from, $to, false, $e->getMessage());
            throw $e;
        }
    }

    public function download(Request $request, Domain $domain)
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $path = $this->resolveFileManagerPath($request);
        if ($path === '') {
            return response()->json(['message' => 'The path field is required.'], 422);
        }

        $result = $this->engine->downloadFile($domain->name, $path);
        if (! empty($result['error'])) {
            $this->logFileAction($request, $domain, 'download', $path, null, false, $result['error']);
            return response()->json(['message' => $result['error']], 422);
        }

        $b64 = (string) ($result['content_base64'] ?? '');
        $bytes = base64_decode($b64, true);
        if ($bytes === false) {
            $this->logFileAction($request, $domain, 'download', $path, null, false, 'invalid base64');
            return response()->json(['message' => 'download failed'], 500);
        }

        $mime = (string) ($result['mime'] ?? 'application/octet-stream');
        $filename = basename((string) ($result['filename'] ?? $path));

        $this->logFileAction($request, $domain, 'download', $path, null, true, null);

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * read / delete / download: path bazen yalnızca query stringde, bazen gövdede gelir;
     * DELETE + axios + bazı proxy'lerde validate() query'yi görmeyebiliyor — QUERY_STRING ile yedeklenir.
     */
    private function resolveFileManagerPath(Request $request): string
    {
        $rawPath = $request->query('path');
        if (is_array($rawPath)) {
            $rawPath = $rawPath[0] ?? null;
        }
        if (! is_string($rawPath) || trim($rawPath) === '') {
            $rawPath = $request->input('path');
            if (is_array($rawPath)) {
                $rawPath = $rawPath[0] ?? null;
            }
        }
        if (! is_string($rawPath) || trim($rawPath) === '') {
            $jp = $request->json('path');
            if (is_string($jp) && trim($jp) !== '') {
                $rawPath = $jp;
            }
        }
        if (! is_string($rawPath) || trim($rawPath) === '') {
            $qs = (string) $request->server('QUERY_STRING', '');
            if ($qs !== '') {
                parse_str($qs, $parsed);
                $fromQs = $parsed['path'] ?? null;
                if (is_array($fromQs)) {
                    $fromQs = $fromQs[0] ?? null;
                }
                if (is_string($fromQs)) {
                    $rawPath = $fromQs;
                }
            }
        }

        return is_string($rawPath) ? trim($rawPath) : '';
    }

    private function logFileAction(
        Request $request,
        Domain $domain,
        string $action,
        ?string $from,
        ?string $to,
        bool $success,
        ?string $error,
    ): void {
        Log::info('panelsar.file_audit', [
            'user_id' => $request->user()?->id,
            'domain' => $domain->name,
            'action' => $action,
            'from' => $from,
            'to' => $to,
            'success' => $success,
            'error' => $error,
            'ip' => $request->ip(),
        ]);
    }
}
