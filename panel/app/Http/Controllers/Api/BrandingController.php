<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PanelSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    public function showPublic(): JsonResponse
    {
        return response()->json($this->brandingPayload());
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
            $url = Storage::disk($disk)->url($path);
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
            'logo_customer_url' => is_string($c) && $c !== '' ? $c : null,
            'logo_admin_url' => is_string($a) && $a !== '' ? $a : null,
        ];
    }
}
