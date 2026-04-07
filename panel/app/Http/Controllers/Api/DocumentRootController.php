<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\DomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentRootController extends Controller
{
    use AuthorizesUserDomain;

    public function __construct(
        private DomainService $domains,
    ) {}

    public function update(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $validated = $request->validate([
            'variant' => ['required', 'string', 'in:root,public'],
        ]);

        $result = $this->domains->setDocumentRootVariant($domain, (string) $validated['variant']);

        if (! empty($result['error'])) {
            return response()->json(['message' => (string) $result['error']], 422);
        }

        return response()->json([
            'domain' => $domain->name,
            'variant' => $result['variant'] ?? (string) $validated['variant'],
            'document_root' => $result['document_root'] ?? $domain->fresh()->document_root,
        ]);
    }
}

