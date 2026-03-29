<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminalController extends Controller
{
    /**
     * Kısa ömürlü JWT üretir; tarayıcı engine WebSocket’ine (?token=) bağlanır.
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
            'iat' => $now,
            'exp' => $now + 120,
        ];

        $jwt = $this->signJwtHs256($payload, $secret);

        $scheme = $request->secure() ? 'wss' : 'ws';
        $url = $scheme.'://'.$request->getHost().'/engine-ws/terminal?token='.rawurlencode($jwt);

        return response()->json([
            'url' => $url,
            'expires_in' => 120,
        ]);
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
}
