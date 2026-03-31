<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PanelSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TerminalController extends Controller
{
    /**
     * Kısa ömürlü JWT üretir; tarayıcı token'ı Sec-WebSocket-Protocol ile taşır.
     * ENGINE_API_SECRET, engine.yaml içindeki security.jwt_secret ile aynı olmalıdır.
     */
    public function session(Request $request): JsonResponse
    {
        $secret = (string) config('panelsar.engine_secret', '');
        if ($secret === '') {
            return response()->json([
                'message' => 'ENGINE_API_SECRET tanımlı değil; panel .env ile engine jwt_secret eşleştirin.',
            ], 503);
        }

        $role = $request->user()->roles->first()?->name ?? 'user';
        if ($role !== 'admin') {
            return response()->json(['message' => 'Bu işlem için yönetici gerekir.'], 403);
        }

        $now = time();
        $payload = [
            'user_id' => $request->user()->id,
            'role' => 'admin',
            'typ' => 'terminal_ws',
            'use_root' => $this->terminalJwtUseRoot(),
            'iat' => $now,
            'exp' => $now + 120,
        ];

        $jwt = $this->signJwtHs256($payload, $secret);

        $scheme = $this->websocketScheme($request);
        $host = $request->getHost();
        $url = $scheme.'://'.$host.'/engine-ws/terminal';

        return response()->json([
            'url' => $url,
            'token' => $jwt,
            'expires_in' => 120,
        ]);
    }

    /** Tarayıcı HTTPS ile açılsa bile Laravel bazen http sanır; wss üretmek için çoklu sinyal. */
    private function websocketScheme(Request $request): string
    {
        $forwarded = strtolower((string) $request->header('X-Forwarded-Proto', ''));
        if ($forwarded === 'https' || str_contains($forwarded, 'https')) {
            return 'wss';
        }
        if (strtolower((string) $request->header('X-Forwarded-Ssl', '')) === 'on') {
            return 'wss';
        }
        $appScheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);
        if (is_string($appScheme) && strtolower($appScheme) === 'https') {
            return 'wss';
        }
        if (config('panelsar.force_wss_terminal') === true) {
            return 'wss';
        }

        return $request->secure() ? 'wss' : 'ws';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signJwtHs256(array $payload, string $secret): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
        $signing = implode('.', $segments);
        $signature = hash_hmac('sha256', $signing, $secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** Panel ayarı: root kabuğu (JWT’de taşınır; engine imzayı doğrular). */
    private function terminalJwtUseRoot(): bool
    {
        if (! Schema::hasTable('panel_settings')) {
            return true;
        }
        $v = PanelSetting::query()->where('key', 'security.terminal_root')->value('value');
        if ($v === null) {
            return true;
        }

        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }
}
