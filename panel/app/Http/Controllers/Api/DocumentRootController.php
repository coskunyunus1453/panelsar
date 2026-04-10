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
            'variant' => ['nullable', 'string', 'in:root,public'],
            'profile' => ['nullable', 'string', 'max:64'],
            'custom_path' => ['nullable', 'string', 'max:255'],
        ]);
        if (! isset($validated['variant']) && ! isset($validated['profile']) && ! isset($validated['custom_path'])) {
            return response()->json(['message' => 'variant, profile veya custom_path gerekli.'], 422);
        }

        $result = $this->domains->setDocumentRootVariant(
            $domain,
            isset($validated['variant']) ? (string) $validated['variant'] : null,
            isset($validated['profile']) ? (string) $validated['profile'] : null,
            isset($validated['custom_path']) ? (string) $validated['custom_path'] : null,
        );

        if (! empty($result['error'])) {
            return response()->json(['message' => (string) $result['error']], 422);
        }

        return response()->json([
            'domain' => $domain->name,
            'variant' => $result['variant'] ?? (isset($validated['variant']) ? (string) $validated['variant'] : null),
            'document_root' => $result['document_root'] ?? $domain->fresh()->document_root,
        ]);
    }
}

