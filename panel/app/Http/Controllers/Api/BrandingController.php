<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PanelSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandingController extends Controller
{
    public function showPublic(): JsonResponse
    {
        return response()->json($this->brandingPayload());
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
        $request->validate([
            'logo_customer' => 'nullable|image|max:2048',
            'logo_admin' => 'nullable|image|max:2048',
        ]);

        $disk = 'public';
        Storage::disk($disk)->makeDirectory('branding');

        $save = function (string $key, $file) use ($disk): void {
            if ($file === null) {
                return;
            }
            $path = $file->store('branding', $disk);
            $basename = basename($path);
            $url = $this->brandingFilePublicPath($basename);
            PanelSetting::query()->updateOrCreate(['key' => $key], ['value' => $url]);
        };

        $save('branding.logo_customer_url', $request->file('logo_customer'));
        $save('branding.logo_admin_url', $request->file('logo_admin'));

        return response()->json([
            'message' => __('settings.branding_saved'),
            'branding' => $this->brandingPayload(),
        ]);
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
        if (preg_match('#/(?:storage/branding|api/branding/files)/([^/?#]+)$#', $value, $m)) {
            $basename = $m[1];
        }

        if ($basename !== null && preg_match('/^[A-Za-z0-9._-]+$/', $basename)
            && Storage::disk('public')->exists('branding/'.$basename)) {
            return $this->brandingFilePublicPath($basename);
        }

        return $value;
    }

    private function brandingFilePublicPath(string $basename): string
    {
        return '/api/branding/files/'.$basename;
    }
}
