<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerWhiteLabel;
use App\Services\WhiteLabelBrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ResellerWhiteLabelController extends Controller
{
    public function __construct(
        private WhiteLabelBrandingService $whiteLabel,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isReseller()) {
            return response()->json(['message' => __('settings.white_label_reseller_only')], 403);
        }

        if (! Schema::hasTable('reseller_white_labels')) {
            return response()->json(['white_label' => null, 'message' => __('settings.branding_table_missing')], 503);
        }

        $row = ResellerWhiteLabel::query()->firstOrNew(['user_id' => $user->id]);

        return response()->json([
            'white_label' => $this->serialize($row),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isReseller()) {
            return response()->json(['message' => __('settings.white_label_reseller_only')], 403);
        }

        if (! Schema::hasTable('reseller_white_labels')) {
            return response()->json(['message' => __('settings.branding_table_missing')], 503);
        }

        $errRef = 'wl-'.bin2hex(random_bytes(4));
        $maxKb = $this->maxUploadKb();

        $validated = $request->validate([
            'slug' => ['nullable', 'string', 'max:64', 'regex:/^([a-z0-9]+(?:-[a-z0-9]+)*)?$/'],
            'hostname' => ['nullable', 'string', 'max:255', 'regex:/^([a-zA-Z0-9.-]*)?$/'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'login_title' => ['nullable', 'string', 'max:200'],
            'login_subtitle' => ['nullable', 'string', 'max:500'],
            'mail_footer_plain' => ['nullable', 'string', 'max:5000'],
            'onboarding_html' => ['nullable', 'string', 'max:20000'],
            'logo_customer' => 'nullable|image|max:'.$maxKb,
            'logo_admin' => 'nullable|image|max:'.$maxKb,
            'clear_logo_customer' => 'sometimes|boolean',
            'clear_logo_admin' => 'sometimes|boolean',
        ]);

        $row = ResellerWhiteLabel::query()->firstOrNew(['user_id' => $user->id]);
        $row->user_id = $user->id;

        if (array_key_exists('slug', $validated)) {
            $slug = $validated['slug'];
            $row->slug = $slug === '' || $slug === null ? null : strtolower((string) $slug);
        }
        if (array_key_exists('hostname', $validated)) {
            $h = $validated['hostname'];
            $row->hostname = $h === '' || $h === null ? null : strtolower((string) $h);
        }
        if (array_key_exists('primary_color', $validated)) {
            $row->primary_color = $validated['primary_color'] ?: null;
        }
        if (array_key_exists('secondary_color', $validated)) {
            $row->secondary_color = $validated['secondary_color'] ?: null;
        }
        if (array_key_exists('login_title', $validated)) {
            $row->login_title = $validated['login_title'] ?: null;
        }
        if (array_key_exists('login_subtitle', $validated)) {
            $row->login_subtitle = $validated['login_subtitle'] ?: null;
        }
        if (array_key_exists('mail_footer_plain', $validated)) {
            $row->mail_footer_plain = $validated['mail_footer_plain'] ?: null;
        }
        if (array_key_exists('onboarding_html', $validated)) {
            $row->onboarding_html = $validated['onboarding_html'] ?: null;
        }

        if ($row->slug) {
            $dup = ResellerWhiteLabel::query()
                ->where('slug', $row->slug)
                ->where('user_id', '!=', $user->id)
                ->exists();
            if ($dup) {
                return response()->json(['message' => __('settings.white_label_slug_taken'), 'error_ref' => $errRef], 422);
            }
        }
        if ($row->hostname) {
            $dup = ResellerWhiteLabel::query()
                ->where('hostname', $row->hostname)
                ->where('user_id', '!=', $user->id)
                ->exists();
            if ($dup) {
                return response()->json(['message' => __('settings.white_label_hostname_taken'), 'error_ref' => $errRef], 422);
            }
        }

        $publicRoot = storage_path('app/public');
        try {
            if (! File::isDirectory($publicRoot)) {
                File::makeDirectory($publicRoot, 0755, true, true);
            }
            Storage::disk('public')->makeDirectory('branding/wl/'.$user->id);
        } catch (Throwable $e) {
            report($e);
            Log::error('reseller_white_label.storage.failed', ['ref' => $errRef, 'message' => $e->getMessage()]);

            return response()->json([
                'message' => __('settings.branding_storage_not_writable'),
                'error_ref' => $errRef,
            ], 507);
        }

        $disk = 'public';
        $dir = 'branding/wl/'.$user->id;

        if ($request->boolean('clear_logo_customer')) {
            $this->deleteOldLogo($disk, $dir, $row->logo_customer_basename);
            $row->logo_customer_basename = null;
        }
        if ($request->boolean('clear_logo_admin')) {
            $this->deleteOldLogo($disk, $dir, $row->logo_admin_basename);
            $row->logo_admin_basename = null;
        }

        try {
            if ($request->hasFile('logo_customer')) {
                $this->deleteOldLogo($disk, $dir, $row->logo_customer_basename);
                $path = $request->file('logo_customer')->store($dir, $disk);
                if ($path !== false && $path !== '') {
                    $row->logo_customer_basename = basename($path);
                }
            }
            if ($request->hasFile('logo_admin')) {
                $this->deleteOldLogo($disk, $dir, $row->logo_admin_basename);
                $path = $request->file('logo_admin')->store($dir, $disk);
                if ($path !== false && $path !== '') {
                    $row->logo_admin_basename = basename($path);
                }
            }
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => __('settings.branding_upload_failed'),
                'error_ref' => $errRef,
            ], 500);
        }

        $row->save();

        return response()->json([
            'message' => __('settings.white_label_saved'),
            'white_label' => $this->serialize($row->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ResellerWhiteLabel $row): array
    {
        $uid = (int) $row->user_id;
        $svc = $this->whiteLabel;

        return [
            'user_id' => $uid,
            'slug' => $row->slug,
            'hostname' => $row->hostname,
            'primary_color' => $row->primary_color,
            'secondary_color' => $row->secondary_color,
            'logo_customer_url' => $svc->publicLogoCustomerUrl($row),
            'logo_admin_url' => $svc->publicLogoAdminUrl($row),
            'login_title' => $row->login_title,
            'login_subtitle' => $row->login_subtitle,
            'mail_footer_plain' => $row->mail_footer_plain,
            'onboarding_html' => $row->onboarding_html,
        ];
    }

    private function deleteOldLogo(string $disk, string $dir, ?string $basename): void
    {
        if (! is_string($basename) || $basename === '' || ! preg_match('/^[A-Za-z0-9._-]+$/', $basename)) {
            return;
        }
        try {
            Storage::disk($disk)->delete($dir.'/'.$basename);
        } catch (Throwable) {
        }
    }

    private function maxUploadKb(): int
    {
        if (! Schema::hasTable('panel_settings')) {
            return 900;
        }
        $raw = \App\Models\PanelSetting::query()->where('key', 'branding.max_upload_kb')->value('value');
        $n = is_numeric($raw) ? (int) $raw : 900;
        if ($n < 128) {
            return 128;
        }
        if ($n > 2048) {
            return 2048;
        }

        return $n;
    }
}
