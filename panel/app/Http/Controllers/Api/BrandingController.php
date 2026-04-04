<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PanelSetting;
use App\Services\SafeAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BrandingController extends Controller
{
    private const DEFAULT_MAX_UPLOAD_KB = 900;

    private const MIN_MAX_UPLOAD_KB = 128;

    private const HARD_CAP_UPLOAD_KB = 2048;

    public function showPublic(): JsonResponse
    {
        if (! Schema::hasTable('panel_settings')) {
            return response()->json([
                'logo_customer_url' => null,
                'logo_admin_url' => null,
            ]);
        }

        try {
            return response()->json($this->brandingPayload());
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'logo_customer_url' => null,
                'logo_admin_url' => null,
            ]);
        }
    }

    /**
     * storage:link olmadan veya statik /storage kökü kapalı olsa bile logolara erişim.
     */
    public function serveFile(string $filename): StreamedResponse
    {
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
            abort(404);
        }

        $relative = 'branding/'.$filename;
        if (! Storage::disk('public')->exists($relative)) {
            abort(404);
        }

        return Storage::disk('public')->response($relative);
    }

    public function update(Request $request): JsonResponse
    {
        $errRef = 'branding-'.bin2hex(random_bytes(4));
        if (! Schema::hasTable('panel_settings')) {
            return response()->json([
                'message' => __('settings.branding_table_missing'),
                'error_ref' => $errRef,
            ], 503);
        }

        try {
            $maxKb = $this->maxUploadKb();
            $request->validate([
                'logo_customer' => 'nullable|image|max:'.$maxKb,
                'logo_admin' => 'nullable|image|max:'.$maxKb,
            ]);
            if (! $request->hasFile('logo_customer') && ! $request->hasFile('logo_admin')) {
                $contentLen = (int) ($request->server('CONTENT_LENGTH') ?? 0);
                $phpUploadBytes = $this->iniSizeToBytes((string) ini_get('upload_max_filesize'));
                $phpPostBytes = $this->iniSizeToBytes((string) ini_get('post_max_size'));
                if ($contentLen > 0 && ($contentLen > $phpUploadBytes || $contentLen > $phpPostBytes)) {
                    return response()->json([
                        'message' => __('settings.branding_upload_failed'),
                        'hint' => sprintf(
                            'Dosya isteği PHP limitini aştı (upload_max_filesize=%s, post_max_size=%s). php.ini değerlerini artırın ve php-fpm/nginx yeniden başlatın.',
                            (string) ini_get('upload_max_filesize'),
                            (string) ini_get('post_max_size')
                        ),
                        'php_upload_max_filesize' => (string) ini_get('upload_max_filesize'),
                        'php_post_max_size' => (string) ini_get('post_max_size'),
                        'content_length' => $contentLen,
                        'error_ref' => $errRef,
                    ], 413);
                }

                return response()->json([
                    'message' => __('settings.branding_upload_failed'),
                    'hint' => 'En az bir logo dosyası seçin (logo_customer veya logo_admin).',
                    'error_ref' => $errRef,
                ], 422);
            }

            $disk = 'public';
            $publicRoot = storage_path('app/public');
            try {
                if (! File::isDirectory($publicRoot)) {
                    File::makeDirectory($publicRoot, 0755, true, true);
                }
                Storage::disk($disk)->makeDirectory('branding');
            } catch (Throwable $e) {
                report($e);
                Log::error('branding.storage.prepare.failed', [
                    'ref' => $errRef,
                    'message' => $e->getMessage(),
                    'public_root' => $publicRoot,
                ]);

                return response()->json([
                    'message' => __('settings.branding_storage_not_writable'),
                    'error_ref' => $errRef,
                ], 507);
            }

            $save = function (string $key, $file) use ($disk): void {
                if ($file === null) {
                    return;
                }
                $path = $file->store('branding', $disk);
                if ($path === false || $path === '') {
                    throw new \RuntimeException('branding file store failed');
                }
                $basename = basename($path);
                $url = $this->brandingFilePublicPath($basename);
                PanelSetting::query()->updateOrCreate(['key' => $key], ['value' => $url]);
            };

            try {
                $save('branding.logo_customer_url', $request->file('logo_customer'));
                $save('branding.logo_admin_url', $request->file('logo_admin'));
            } catch (Throwable $e) {
                report($e);
                Log::error('branding.upload.failed', array_filter([
                    'ref' => $errRef,
                    'message' => $e->getMessage(),
                    'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                    'logo_customer_error' => $request->file('logo_customer')?->getError(),
                    'logo_admin_error' => $request->file('logo_admin')?->getError(),
                ]));

                return response()->json([
                    'message' => __('settings.branding_upload_failed'),
                    'max_upload_kb' => $maxKb,
                    'hint' => __('settings.branding_storage_not_writable'),
                    'debug_error' => config('app.debug') ? $e->getMessage() : null,
                    'error_ref' => $errRef,
                ], 500);
            }

            return response()->json([
                'message' => __('settings.branding_saved'),
                'branding' => $this->brandingPayload(),
                'max_upload_kb' => $maxKb,
            ]);
        } catch (Throwable $e) {
            report($e);
            Log::critical('branding.update.unhandled', SafeAuditLogger::sanitizeContext(array_filter([
                'ref' => $errRef,
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                'content_length' => (int) ($request->server('CONTENT_LENGTH') ?? 0),
                'php_upload_max_filesize' => (string) ini_get('upload_max_filesize'),
                'php_post_max_size' => (string) ini_get('post_max_size'),
                'tmp_dir' => (string) (ini_get('upload_tmp_dir') ?: sys_get_temp_dir()),
            ])));

            return response()->json([
                'message' => __('settings.branding_upload_failed'),
                'hint' => 'Beklenmeyen sunucu hatası. error_ref ile logdan takip edin.',
                'debug_error' => config('app.debug') ? $e->getMessage() : null,
                'error_ref' => $errRef,
            ], 500);
        }
    }

    public function config(): JsonResponse
    {
        if (! Schema::hasTable('panel_settings')) {
            return response()->json([
                'max_upload_kb' => self::DEFAULT_MAX_UPLOAD_KB,
            ]);
        }

        return response()->json([
            'max_upload_kb' => $this->maxUploadKb(),
        ]);
    }

    public function updateConfig(Request $request): JsonResponse
    {
        if (! Schema::hasTable('panel_settings')) {
            return response()->json([
                'message' => __('settings.branding_table_missing'),
            ], 503);
        }
        $validated = $request->validate([
            'max_upload_kb' => 'required|integer|min:'.self::MIN_MAX_UPLOAD_KB.'|max:'.self::HARD_CAP_UPLOAD_KB,
        ]);
        PanelSetting::query()->updateOrCreate(
            ['key' => 'branding.max_upload_kb'],
            ['value' => (string) ((int) $validated['max_upload_kb'])]
        );

        return response()->json([
            'message' => __('settings.branding_config_saved'),
            'max_upload_kb' => (int) $validated['max_upload_kb'],
        ]);
    }

    public function diagnostics(): JsonResponse
    {
        $checks = [];
        $publicRoot = storage_path('app/public');
        $brandingDir = $publicRoot.'/branding';

        $checks[] = [
            'key' => 'panel_settings_table',
            'ok' => Schema::hasTable('panel_settings'),
            'message' => Schema::hasTable('panel_settings') ? 'panel_settings table exists' : 'panel_settings table missing',
        ];

        try {
            if (! File::isDirectory($publicRoot)) {
                File::makeDirectory($publicRoot, 0755, true, true);
            }
            if (! File::isDirectory($brandingDir)) {
                File::makeDirectory($brandingDir, 0755, true, true);
            }
            $checks[] = [
                'key' => 'branding_dir_exists',
                'ok' => true,
                'message' => 'branding directory exists',
            ];
        } catch (Throwable $e) {
            $checks[] = [
                'key' => 'branding_dir_exists',
                'ok' => false,
                'message' => 'branding directory cannot be created: '.$e->getMessage(),
            ];
        }

        $tmpFile = $brandingDir.'/.write-test-'.uniqid('', true);
        try {
            File::put($tmpFile, 'ok');
            File::delete($tmpFile);
            $checks[] = [
                'key' => 'branding_write_test',
                'ok' => true,
                'message' => 'branding directory is writable',
            ];
        } catch (Throwable $e) {
            $checks[] = [
                'key' => 'branding_write_test',
                'ok' => false,
                'message' => 'branding directory is not writable: '.$e->getMessage(),
            ];
        }

        try {
            Storage::disk('public')->makeDirectory('branding');
            $checks[] = [
                'key' => 'public_disk',
                'ok' => true,
                'message' => 'public disk available',
            ];
        } catch (Throwable $e) {
            $checks[] = [
                'key' => 'public_disk',
                'ok' => false,
                'message' => 'public disk error: '.$e->getMessage(),
            ];
        }

        $checks[] = [
            'key' => 'php_upload_limits',
            'ok' => true,
            'message' => sprintf(
                'upload_max_filesize=%s, post_max_size=%s, max_file_uploads=%s, upload_tmp_dir=%s',
                (string) ini_get('upload_max_filesize'),
                (string) ini_get('post_max_size'),
                (string) ini_get('max_file_uploads'),
                (string) (ini_get('upload_tmp_dir') ?: sys_get_temp_dir())
            ),
        ];

        $ok = collect($checks)->every(fn ($c) => (bool) ($c['ok'] ?? false));

        return response()->json(['ok' => $ok, 'checks' => $checks]);
    }

    /**
     * @return array{logo_customer_url: ?string, logo_admin_url: ?string}
     */
    private function brandingPayload(): array
    {
        $c = PanelSetting::query()->where('key', 'branding.logo_customer_url')->value('value');
        $a = PanelSetting::query()->where('key', 'branding.logo_admin_url')->value('value');

        return [
            'logo_customer_url' => $this->normalizeBrandingUrl($c),
            'logo_admin_url' => $this->normalizeBrandingUrl($a),
        ];
    }

    private function normalizeBrandingUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $basename = null;
        if (preg_match('#/(?:storage/branding|api/branding/files)/([^/?\#]+)$#', $value, $m)) {
            $basename = $m[1];
        }

        if ($basename !== null && preg_match('/^[A-Za-z0-9._-]+$/', $basename)) {
            try {
                if (Storage::disk('public')->exists('branding/'.$basename)) {
                    return $this->brandingFilePublicPath($basename);
                }
            } catch (Throwable) {
                // disk kökü veya izin sorununda ham değeri döndür
            }
        }

        return $value;
    }

    private function brandingFilePublicPath(string $basename): string
    {
        // Panel alt dizinde (ör. `/proje/panel/public`) servis edilebiliyor.
        // Mutlak `/api/...` path’i kökten arar ve 404 üretir.
        // Bu yüzden göreli path döndürüyoruz.
        return 'api/branding/files/'.$basename;
    }

    private function maxUploadKb(): int
    {
        $raw = PanelSetting::query()->where('key', 'branding.max_upload_kb')->value('value');
        $n = is_numeric($raw) ? (int) $raw : self::DEFAULT_MAX_UPLOAD_KB;
        if ($n < self::MIN_MAX_UPLOAD_KB) {
            return self::MIN_MAX_UPLOAD_KB;
        }
        if ($n > self::HARD_CAP_UPLOAD_KB) {
            return self::HARD_CAP_UPLOAD_KB;
        }

        return $n;
    }

    private function iniSizeToBytes(string $value): int
    {
        $v = trim($value);
        if ($v === '') {
            return 0;
        }
        $unit = strtolower(substr($v, -1));
        $num = (float) $v;

        return match ($unit) {
            'g' => (int) round($num * 1024 * 1024 * 1024),
            'm' => (int) round($num * 1024 * 1024),
            'k' => (int) round($num * 1024),
            default => (int) $num,
        };
    }
}
