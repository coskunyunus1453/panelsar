<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\DnsRecord;
use App\Models\Domain;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DnsRecordController extends Controller
{
    use AuthorizesUserDomain;

    public function __construct(
        private EngineApiService $engine,
    ) {}

    public function index(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        return response()->json([
            'records' => $domain->dnsRecords,
            'engine_preview' => $this->engine->dnsList($domain->name),
        ]);
    }

    public function store(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $validated = $request->validate([
            'type' => 'required|string|max:10',
            'name' => 'required|string|max:255',
            'value' => 'required|string',
            'ttl' => 'nullable|integer|min:60',
            'priority' => 'nullable|integer',
        ]);

        $record = $domain->dnsRecords()->create($validated);

        $enginePayload = array_merge($validated, ['id' => (string) $record->id]);

        return response()->json([
            'message' => __('dns.created'),
            'record' => $record,
            'engine' => $this->engine->dnsCreate($domain->name, $enginePayload),
        ], 201);
    }

    public function destroy(Request $request, DnsRecord $dnsRecord): JsonResponse
    {
        $domain = $dnsRecord->domain;
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $id = (string) $dnsRecord->id;
        $dnsRecord->delete();

        return response()->json([
            'message' => __('dns.deleted'),
            'engine' => $this->engine->dnsDeleteRecord($domain->name, $id),
        ]);
    }
}
