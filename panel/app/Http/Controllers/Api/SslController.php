<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\SslCertificate;
use App\Services\EngineApiService;
use App\Services\HostingQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SslController extends Controller
{
    use AuthorizesUserDomain;

    public function __construct(
        private EngineApiService $engine,
        private HostingQuotaService $quota,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $domainIds = $request->user()->domains()->pluck('id');
        $certs = SslCertificate::whereIn('domain_id', $domainIds)->with('domain')->get();

        return response()->json(['certificates' => $certs]);
    }

    public function issue(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $this->quota->ensureSslAllowed($request->user());

        $validated = $request->validate([
            'email' => 'nullable|email',
        ]);
        $email = $validated['email'] ?? null;
        if ($email === null || $email === '') {
            $email = config('panelsar.lets_encrypt_email') ?: null;
        }
        if ($email === null || $email === '') {
            // Panelde email girmeden SSL başlatılabiliyor; fallback olarak kullanıcının e-posta adresini kullan.
            $email = $request->user()?->email;
        }
        if ($email === null || $email === '') {
            return response()->json([
                'message' => __('ssl.email_required'),
            ], 422);
        }

        $cert = SslCertificate::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'provider' => 'letsencrypt',
                'type' => 'dv',
                'status' => 'pending',
                'auto_renew' => true,
            ]
        );

        $engine = $this->engine->issueSSL($domain->name, is_string($email) ? $email : null);
        if (! empty($engine['error'])) {
            $cert->update(['status' => 'failed']);
            $domain->update(['ssl_enabled' => false, 'ssl_expiry' => null]);

            return response()->json([
                'message' => $engine['error'],
                'certificate' => $cert->fresh(),
            ], 503);
        }

        $cert->update(['status' => 'active', 'issued_at' => now(), 'expires_at' => now()->addDays(90)]);
        $domain->update([
            'ssl_enabled' => true,
            'ssl_expiry' => $cert->expires_at,
        ]);

        return response()->json([
            'message' => __('ssl.issued'),
            'certificate' => $cert->fresh(),
            'engine' => $engine,
        ]);
    }

    public function renew(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $this->quota->ensureSslAllowed($request->user());

        $cert = $domain->sslCertificate;
        if (! $cert) {
            return response()->json(['message' => __('ssl.missing')], 404);
        }

        $engine = $this->engine->renewSSL($domain->name);
        if (! empty($engine['error'])) {
            return response()->json([
                'message' => $engine['error'],
                'certificate' => $cert->fresh(),
            ], 503);
        }

        return response()->json([
            'message' => __('ssl.renewed'),
            'engine' => $engine,
            'certificate' => $cert->fresh(),
        ]);
    }

    public function revoke(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $engine = $this->engine->revokeSSL($domain->name);
        if (! empty($engine['error'])) {
            return response()->json(['message' => $engine['error']], 503);
        }

        $domain->sslCertificate?->delete();
        $domain->update([
            'ssl_enabled' => false,
            'ssl_expiry' => null,
        ]);

        return response()->json([
            'message' => __('ssl.revoked'),
            'engine' => $engine,
        ]);
    }
}
