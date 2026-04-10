<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\PanelSetting;
use App\Services\AutoWebConfigurator;
use App\Services\EngineApiService;
use App\Services\HostingQuotaService;
use App\Services\SafeAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FileManagerController extends Controller
{
    use AuthorizesUserDomain;

    private const TRASH_DIR = '.hostvim-trash';

    private const TRASH_ITEMS_DIR = '.hostvim-trash/items';

    private const TRASH_META_DIR = '.hostvim-trash/meta';

    public function __construct(
        private EngineApiService $engine,
        private HostingQuotaService $quota,
        private AutoWebConfigurator $autoWebConfigurator,
    ) {}

    /**
     * Panelin “site document_root altına göreli path” gönderdiği varsayımıyla,
     * engine'in “engine web_root/domain altına göreli path” beklediği çeviriyi yapar.
     */
    private function panelRelToEngineRel(Domain $domain, string $panelRel): string
    {
        $hostingRoot = rtrim((string) config('hostvim.hosting_web_root'), '/\\');
        $engineRoot = $hostingRoot.DIRECTORY_SEPARATOR.$domain->name;

        // Domain.document_root panelde tam path (örn. /var/www/example.com/public_html)
        $docRoot = (string) $domain->document_root;

        $panelRelNorm = str_replace('\\', '/', trim($panelRel));
        $panelRelNorm = ltrim($panelRelNorm, '/'); // engine tarafı leading slash'ları temizliyor ama biz net olsun diye

        $engineRootNorm = str_replace('\\', '/', $engineRoot);
        $docRootNorm = str_replace('\\', '/', $docRoot);

        $baseRel = '';
        if ($docRootNorm === $engineRootNorm) {
            $baseRel = '';
        } elseif (str_starts_with($docRootNorm, $engineRootNorm.'/')) {
            $baseRel = substr($docRootNorm, strlen($engineRootNorm) + 1);
        } else {
            // Fallback: document_root hostingRoot/domain altında mı?
            $expectedPrefix = $hostingRoot.'/'.$domain->name.'/';
            if ($hostingRoot !== '' && str_starts_with($docRootNorm, $expectedPrefix)) {
                $baseRel = substr($docRootNorm, strlen($expectedPrefix));
            }
        }

        if ($baseRel === '') {
            return $panelRelNorm;
        }

        if ($panelRelNorm === '') {
            return $baseRel;
        }

        return $baseRel.'/'.$panelRelNorm;
    }

    public function index(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        // Dizin listesi: read/delete/download ile aynı çözümleme (bazı proxy/SAPI’lerde query('path') boş kalabiliyor).
        $path = $this->resolveFileManagerPath($request);
        $engineRelPath = $this->panelRelToEngineRel($domain, $path);

        $limit = (int) $request->query('limit', 200);
        $offset = (int) $request->query('offset', 0);
        $sort = (string) $request->query('sort', 'name');
        $order = (string) $request->query('order', 'asc');

        $limit = max(1, min(5000, $limit));
        $offset = max(0, $offset);
        $sort = in_array($sort, ['name', 'size', 'mtime'], true) ? $sort : 'name';
        $order = strtolower($order) === 'desc' ? 'desc' : 'asc';

        $list = $this->engine->listFilesResult($domain->name, $engineRelPath, $limit, $offset, $sort, $order);
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
                $this->panelRelToEngineRel($domain, (string) ($validated['path'] ?? '')),
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

        $enginePath = $this->panelRelToEngineRel($domain, $validated['path']);
        $templateMode = $this->inferSiblingMode($domain, $enginePath, true);
        $result = $this->engine->mkdirFile($domain->name, $enginePath);
        if (! empty($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }
        if ($templateMode !== null) {
            $this->engine->chmodFile($domain->name, $enginePath, $templateMode);
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
        $engineFrom = $this->panelRelToEngineRel($domain, $from);
        try {
            $result = $this->engine->deleteFile($domain->name, $engineFrom);
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

    public function trashIndex(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $limit = (int) $request->query('limit', 200);
        $limit = max(1, min(200, $limit));

        $metaList = $this->engine->listFilesResult($domain->name, self::TRASH_META_DIR, $limit, 0, 'mtime', 'desc');
        if ($metaList['error'] !== null) {
            // Trash hiç yoksa boş dön.
            return response()->json(['items' => []]);
        }

        $items = [];
        foreach (($metaList['entries'] ?? []) as $e) {
            $name = (string) ($e['name'] ?? '');
            $isDir = (bool) ($e['is_dir'] ?? false);
            if ($name === '' || $isDir) {
                continue;
            }
            if (! str_ends_with($name, '.json')) {
                continue;
            }

            $id = substr($name, 0, -5);
            if ($id === '') {
                continue;
            }

            try {
                $raw = $this->engine->readFile($domain->name, self::TRASH_META_DIR.'/'.$name);
                $meta = json_decode($raw, true);
                if (! is_array($meta)) {
                    continue;
                }
                $items[] = [
                    'id' => $id,
                    'original_path' => (string) ($meta['original_path'] ?? ''),
                    'deleted_at' => (string) ($meta['deleted_at'] ?? ''),
                    'name' => (string) ($meta['name'] ?? ''),
                    'is_dir' => (bool) ($meta['is_dir'] ?? false),
                    'size' => (int) ($meta['size'] ?? 0),
                ];
            } catch (\Throwable $ignored) {
                continue;
            }
        }

        return response()->json(['items' => $items]);
    }

    public function trashMove(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $validated = $request->validate([
            'path' => 'required|string|max:2048',
        ]);

        $from = trim((string) $validated['path']);
        if ($from === '') {
            return response()->json(['message' => 'The path field is required.'], 422);
        }

        $id = now()->format('YmdHis').'-'.Str::lower(Str::random(10));
        $engineFrom = $this->panelRelToEngineRel($domain, $from);
        $engineItem = self::TRASH_ITEMS_DIR.'/'.$id;
        $engineMeta = self::TRASH_META_DIR.'/'.$id.'.json';

        try {
            // Trash klasörlerini garanti et.
            $this->engine->mkdirFile($domain->name, self::TRASH_DIR);
            $this->engine->mkdirFile($domain->name, self::TRASH_ITEMS_DIR);
            $this->engine->mkdirFile($domain->name, self::TRASH_META_DIR);

            $mv = $this->engine->moveFile($domain->name, $engineFrom, $engineItem);
            if (! empty($mv['error'])) {
                $this->logFileAction($request, $domain, 'trash_move', $from, null, false, $mv['error']);

                return response()->json(['message' => $mv['error']], 422);
            }

            $metaPayload = [
                'id' => $id,
                'original_path' => $from,
                'deleted_at' => now()->toIso8601String(),
                'name' => basename($from),
                // dosya/klasör ayrımı engine tarafında list ile net; burada best-effort
            ];
            $wr = $this->engine->writeFile($domain->name, $engineMeta, json_encode($metaPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            if (! empty($wr['error'])) {
                // Meta yazılamadıysa bile dosya trash’e taşındı; kullanıcı restore edemeyebilir.
                $this->logFileAction($request, $domain, 'trash_meta_write', $from, null, false, $wr['error']);
            }

            $this->logFileAction($request, $domain, 'trash_move', $from, null, true, null);

            return response()->json(['id' => $id, 'message' => 'moved_to_trash']);
        } catch (\Throwable $e) {
            $this->logFileAction($request, $domain, 'trash_move', $from, null, false, $e->getMessage());
            throw $e;
        }
    }

    public function trashRestore(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $validated = $request->validate([
            'id' => 'required|string|max:64',
        ]);
        $id = trim((string) $validated['id']);
        if ($id === '') {
            return response()->json(['message' => 'The id field is required.'], 422);
        }

        $engineMeta = self::TRASH_META_DIR.'/'.$id.'.json';
        $engineItem = self::TRASH_ITEMS_DIR.'/'.$id;

        try {
            $raw = $this->engine->readFile($domain->name, $engineMeta);
            $meta = json_decode($raw, true);
            $origPanel = is_array($meta) ? (string) ($meta['original_path'] ?? '') : '';
            if (trim($origPanel) === '') {
                return response()->json(['message' => 'Restore bilgisi bulunamadı.'], 404);
            }

            $engineTo = $this->panelRelToEngineRel($domain, $origPanel);
            $mv = $this->engine->moveFile($domain->name, $engineItem, $engineTo);
            if (! empty($mv['error'])) {
                // Çakışma varsa alternatif isme restore et.
                $altPanel = $origPanel.'.restored-'.now()->format('YmdHis');
                $altEngine = $this->panelRelToEngineRel($domain, $altPanel);
                $mv2 = $this->engine->moveFile($domain->name, $engineItem, $altEngine);
                if (! empty($mv2['error'])) {
                    $this->logFileAction($request, $domain, 'trash_restore', $origPanel, null, false, $mv2['error']);

                    return response()->json(['message' => $mv2['error']], 422);
                }
            }

            // Meta'yı sil.
            $this->engine->deleteFile($domain->name, $engineMeta);

            $this->logFileAction($request, $domain, 'trash_restore', $origPanel, null, true, null);

            return response()->json(['message' => 'restored']);
        } catch (\Throwable $e) {
            $this->logFileAction($request, $domain, 'trash_restore', $id, null, false, $e->getMessage());
            throw $e;
        }
    }

    public function trashDestroy(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $id = (string) $request->query('id', '');
        $id = trim($id);
        if ($id === '') {
            return response()->json(['message' => 'The id field is required.'], 422);
        }

        $engineMeta = self::TRASH_META_DIR.'/'.$id.'.json';
        $engineItem = self::TRASH_ITEMS_DIR.'/'.$id;

        try {
            $r1 = $this->engine->deleteFile($domain->name, $engineItem);
            $this->engine->deleteFile($domain->name, $engineMeta);
            if (! empty($r1['error'])) {
                $this->logFileAction($request, $domain, 'trash_delete', $id, null, false, $r1['error']);

                return response()->json(['message' => $r1['error']], 422);
            }
            $this->logFileAction($request, $domain, 'trash_delete', $id, null, true, null);

            return response()->json(['message' => 'deleted']);
        } catch (\Throwable $e) {
            $this->logFileAction($request, $domain, 'trash_delete', $id, null, false, $e->getMessage());
            throw $e;
        }
    }

    public function trashEmpty(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        try {
            $r = $this->engine->deleteFile($domain->name, self::TRASH_DIR);
            if (! empty($r['error'])) {
                $this->logFileAction($request, $domain, 'trash_empty', null, null, false, $r['error']);

                return response()->json(['message' => $r['error']], 422);
            }
            $this->logFileAction($request, $domain, 'trash_empty', null, null, true, null);

            return response()->json(['message' => 'emptied']);
        } catch (\Throwable $e) {
            $this->logFileAction($request, $domain, 'trash_empty', null, null, false, $e->getMessage());
            throw $e;
        }
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
        $enginePath = $this->panelRelToEngineRel($domain, $path);

        return response()->json([
            'content' => $this->engine->readFile($domain->name, $enginePath),
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
        $engineFrom = $this->panelRelToEngineRel($domain, $from);
        $this->quota->ensureDiskHeadroom($request->user(), strlen((string) ($validated['content'] ?? '')));
        try {
            $result = $this->engine->writeFile($domain->name, $engineFrom, $validated['content'] ?? '');
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
        $engineFrom = $this->panelRelToEngineRel($domain, $from);
        $this->quota->ensureDiskHeadroom($request->user(), strlen((string) ($validated['content'] ?? '')));
        try {
            $result = $this->engine->createFile($domain->name, $engineFrom, $validated['content'] ?? '');
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
        $maxKb = $this->fileManagerMaxUploadKb();
        $validated = $request->validate([
            'path' => 'nullable|string',
            'file' => 'required|file|max:'.$maxKb,
        ]);
        $relPath = (string) ($validated['path'] ?? '');
        $engineRelPath = $this->panelRelToEngineRel($domain, $relPath);
        // Klasör sürükle-bırakta derin/yeni dizinler gelebilir; upload öncesi dizini server tarafında garanti et.
        if (trim($engineRelPath) !== '') {
            $mk = $this->engine->mkdirFile($domain->name, $engineRelPath);
            if (! empty($mk['error']) && ! str_contains(strtolower((string) $mk['error']), 'exist')) {
                return response()->json(['message' => $mk['error']], 422);
            }
        }
        $up = $request->file('file');
        $baseName = basename((string) $up->getClientOriginalName());
        $engineTargetPath = trim($engineRelPath !== '' ? $engineRelPath.'/'.$baseName : $baseName, '/');
        $templateMode = $this->inferSiblingMode($domain, $engineTargetPath, false);
        $this->quota->ensureDiskHeadroom($request->user(), (int) $up->getSize());
        try {
            $result = $this->engine->uploadFile($domain->name, $engineRelPath, $up);
            $ok = empty($result['error']);
            $auto = null;
            if ($ok && $templateMode !== null) {
                $this->engine->chmodFile($domain->name, $engineTargetPath, $templateMode);
            }
            if ($ok && $this->shouldAutoConfigureAfterUpload($baseName)) {
                $auto = $this->autoWebConfigurator->detectAndApply($domain->fresh());
                if (! ($auto['applied'] ?? false)) {
                    SafeAuditLogger::warning('hostvim.file_audit', [
                        'domain' => $domain->name,
                        'action' => 'auto_web_config_after_upload_failed',
                        'error' => (string) ($auto['error'] ?? 'unknown'),
                        'profile' => (string) ($auto['profile'] ?? ''),
                        'variant' => (string) ($auto['variant'] ?? ''),
                    ], $request);
                }
            }
            $this->logFileAction($request, $domain, 'upload', $relPath, null, $ok, $result['error'] ?? null);
            if ($auto !== null) {
                $result['auto_web'] = $auto;
            }

            $status = $ok ? 200 : 502;

            return response()->json($result, $status);
        } catch (\Throwable $e) {
            $this->logFileAction($request, $domain, 'upload', $relPath, null, false, $e->getMessage());
            throw $e;
        }
    }

    private function fileManagerMaxUploadKb(): int
    {
        $configured = (int) config('hostvim.limits.max_file_manager_size_mb', 50);
        $panelOverride = (int) (PanelSetting::query()->where('key', 'limits.max_file_manager_size_mb')->value('value') ?? 0);
        $mb = max(1, $panelOverride > 0 ? $panelOverride : $configured);

        return $mb * 1024;
    }

    /**
     * Hedef dizindeki kardeş dosya/klasörlerden mode şablonu bulur (örn 644/755).
     */
    private function inferSiblingMode(Domain $domain, string $engineTargetPath, bool $wantDir): ?string
    {
        $target = trim(str_replace('\\', '/', $engineTargetPath), '/');
        if ($target === '') {
            return null;
        }
        $base = basename($target);
        $targetExt = $wantDir ? '' : $this->fileExt($base);
        $pos = strrpos($target, '/');
        $parent = $pos === false ? '' : substr($target, 0, $pos);

        $list = $this->engine->listFilesResult($domain->name, $parent, 500, 0, 'name', 'asc');
        if (($list['error'] ?? null) !== null) {
            return null;
        }

        $sameExtMode = null;
        $anyMode = null;
        foreach (($list['entries'] ?? []) as $e) {
            $name = (string) ($e['name'] ?? '');
            if ($name === '' || $name === $base) {
                continue;
            }
            if ((bool) ($e['is_dir'] ?? false) !== $wantDir) {
                continue;
            }
            $mode = $this->normalizeMode((string) ($e['mode'] ?? ''));
            if ($mode === null) {
                continue;
            }
            if ($wantDir) {
                return $mode;
            }
            if ($anyMode === null) {
                $anyMode = $mode;
            }
            if ($targetExt !== '' && $this->fileExt($name) === $targetExt) {
                $sameExtMode = $mode;
                break;
            }
        }

        return $sameExtMode ?? $anyMode;
    }

    private function normalizeMode(string $raw): ?string
    {
        $m = trim($raw);
        if ($m === '') {
            return null;
        }
        if (preg_match('/^[0-7]{3,4}$/', $m) === 1) {
            return strlen($m) === 4 ? substr($m, 1) : $m;
        }

        return null;
    }

    private function fileExt(string $name): string
    {
        $dot = strrpos($name, '.');
        if ($dot === false || $dot === 0 || $dot === strlen($name) - 1) {
            return '';
        }

        return strtolower(substr($name, $dot + 1));
    }

    private function shouldAutoConfigureAfterUpload(string $filename): bool
    {
        $f = strtolower(trim($filename));
        if ($f === '') {
            return false;
        }
        $markers = [
            'artisan',
            'composer.json',
            'package.json',
            'wp-config.php',
            'next.config.js',
            'next.config.mjs',
            'next.config.ts',
            'nuxt.config.js',
            'nuxt.config.ts',
            '.env',
        ];
        if (in_array($f, $markers, true)) {
            return true;
        }

        return str_ends_with($f, '.zip')
            || str_ends_with($f, '.tar')
            || str_ends_with($f, '.tar.gz')
            || str_ends_with($f, '.tgz');
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
        $engineFrom = $this->panelRelToEngineRel($domain, $from);
        $engineTo = $this->panelRelToEngineRel($domain, $to);
        try {
            $result = $this->engine->renameFile($domain->name, $engineFrom, $engineTo);
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
        $engineFrom = $this->panelRelToEngineRel($domain, $from);
        $engineTo = $this->panelRelToEngineRel($domain, $to);
        try {
            $result = $this->engine->moveFile($domain->name, $engineFrom, $engineTo);
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

    public function copy(Request $request, Domain $domain): JsonResponse
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
        $engineFrom = $this->panelRelToEngineRel($domain, $from);
        $engineTo = $this->panelRelToEngineRel($domain, $to);
        $this->quota->ensureDiskHeadroom($request->user(), $this->quota->engineFileSizeBytes($domain->name, $engineFrom));
        $result = $this->engine->copyFile($domain->name, $engineFrom, $engineTo);
        if (! empty($result['error'])) {
            $this->logFileAction($request, $domain, 'copy', $from, $to, false, $result['error']);

            return response()->json(['message' => $result['error']], 422);
        }
        $this->logFileAction($request, $domain, 'copy', $from, $to, true, null);

        return response()->json($result);
    }

    public function chmod(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $validated = $request->validate([
            'path' => 'required|string',
            'mode' => 'required|string|regex:/^[0-7]{3,4}$/',
        ]);
        $path = $validated['path'];
        $mode = $validated['mode'];
        $enginePath = $this->panelRelToEngineRel($domain, $path);
        $result = $this->engine->chmodFile($domain->name, $enginePath, $mode);
        if (! empty($result['error'])) {
            $this->logFileAction($request, $domain, 'chmod', $path, null, false, $result['error']);

            return response()->json(['message' => $result['error']], 422);
        }
        $this->logFileAction($request, $domain, 'chmod', $path, null, true, null);

        return response()->json($result);
    }

    public function zip(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $validated = $request->validate([
            'source' => 'required|string',
            'target' => 'required|string',
        ]);
        $source = $validated['source'];
        $target = $validated['target'];
        $result = $this->engine->zipPath(
            $domain->name,
            $this->panelRelToEngineRel($domain, $source),
            $this->panelRelToEngineRel($domain, $target)
        );
        if (! empty($result['error'])) {
            // Aynı isimde zip varsa otomatik olarak benzersiz isimle bir kez daha dene.
            if (str_contains(strtolower((string) $result['error']), 'target already exists')) {
                $dot = strrpos($target, '.');
                $base = $dot !== false ? substr($target, 0, $dot) : $target;
                $ext = $dot !== false ? substr($target, $dot) : '.zip';
                $retryTarget = $base.'-'.now()->format('YmdHis').$ext;

                $retry = $this->engine->zipPath(
                    $domain->name,
                    $this->panelRelToEngineRel($domain, $source),
                    $this->panelRelToEngineRel($domain, $retryTarget)
                );
                if (empty($retry['error'])) {
                    $this->logFileAction($request, $domain, 'zip', $source, $retryTarget, true, null);

                    return response()->json([
                        'message' => 'zip created',
                        'target' => $retryTarget,
                    ]);
                }
                $result = $retry;
            }

            $this->logFileAction($request, $domain, 'zip', $source, $target, false, $result['error']);

            return response()->json(['message' => $result['error']], 422);
        }
        $this->logFileAction($request, $domain, 'zip', $source, $target, true, null);

        return response()->json($result);
    }

    public function unzip(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $validated = $request->validate([
            'archive' => 'required|string',
            'target_dir' => 'nullable|string',
            'targetDir' => 'nullable|string',
            'if_exists' => 'nullable|string|in:fail,overwrite,skip',
        ]);
        $archive = $validated['archive'];
        $targetDir = (string) ($validated['target_dir'] ?? $validated['targetDir'] ?? '');
        $ifExists = (string) ($validated['if_exists'] ?? 'fail');
        $engineArchive = $this->panelRelToEngineRel($domain, $archive);
        $this->quota->ensureDiskHeadroom($request->user(), $this->quota->estimatedUnzipHeadroomBytes($domain->name, $engineArchive));
        $result = $this->engine->unzipPath(
            $domain->name,
            $engineArchive,
            $this->panelRelToEngineRel($domain, $targetDir),
            $ifExists
        );
        if (! empty($result['error'])) {
            $this->logFileAction($request, $domain, 'unzip', $archive, $targetDir, false, $result['error']);

            return response()->json(['message' => $result['error']], 422);
        }
        $auto = $this->autoWebConfigurator->detectAndApply($domain->fresh());
        if (! ($auto['applied'] ?? false)) {
            SafeAuditLogger::warning('hostvim.file_audit', [
                'domain' => $domain->name,
                'action' => 'auto_web_config_after_unzip_failed',
                'error' => (string) ($auto['error'] ?? 'unknown'),
                'profile' => (string) ($auto['profile'] ?? ''),
                'variant' => (string) ($auto['variant'] ?? ''),
            ], $request);
        }
        $result['auto_web'] = $auto;
        $this->logFileAction($request, $domain, 'unzip', $archive, $targetDir, true, null);

        return response()->json($result);
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
        $enginePath = $this->panelRelToEngineRel($domain, $path);

        $result = $this->engine->downloadFile($domain->name, $enginePath);
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
        SafeAuditLogger::info('hostvim.file_audit', [
            'domain' => $domain->name,
            'action' => $action,
            'from_fp' => SafeAuditLogger::pathFingerprint($domain->name, $from),
            'from_base' => SafeAuditLogger::pathBasename($from),
            'to_fp' => SafeAuditLogger::pathFingerprint($domain->name, $to),
            'to_base' => SafeAuditLogger::pathBasename($to),
            'success' => $success,
            'error' => $error,
        ], $request);
    }
}
