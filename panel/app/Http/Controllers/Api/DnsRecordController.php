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

    /**
     * BIND benzeri metin (paneldeki kayıtlar; SOA/NS sağlayıcıda tanımlanmalı).
     */
    public function exportZone(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $domain->load(['dnsRecords' => static fn ($q) => $q->orderBy('type')->orderBy('name')]);

        return response()->json([
            'domain' => $domain->name,
            'format' => 'bind-lite',
            'zone' => $this->bindZoneText($domain),
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

    private function bindZoneText(Domain $domain): string
    {
        $zone = strtolower(trim($domain->name));
        $lines = [
            '; DNS zone export — '.$zone.' ('.__('dns.zone_export_panel_records').')',
            '; '.__('dns.zone_export_soa_hint'),
            '$ORIGIN '.$zone.'.',
            '$TTL 3600',
            '',
        ];
        foreach ($domain->dnsRecords as $r) {
            $fqdn = $this->dnsNameToFqdn($zone, (string) $r->name);
            $ttl = max(60, (int) ($r->ttl ?: 3600));
            $type = strtoupper(trim((string) $r->type));
            $val = trim((string) $r->value);
            if ($type === 'TXT' && $val !== '') {
                $val = '"'.str_replace('"', '\\"', $val).'"';
            }
            if ($type === 'MX') {
                $pri = (int) ($r->priority ?? 10);
                $target = $this->mxTargetFqdn($val);

                $lines[] = sprintf('%s %d IN MX %d %s', $fqdn, $ttl, $pri, $target);
            } else {
                $lines[] = sprintf('%s %d IN %s %s', $fqdn, $ttl, $type, $val);
            }
        }

        return implode("\n", $lines)."\n";
    }

    private function dnsNameToFqdn(string $zone, string $name): string
    {
        $name = strtolower(trim($name));
        if ($name === '' || $name === '@') {
            return $zone.'.';
        }
        if (str_ends_with($name, '.')) {
            return $name;
        }

        return $name.'.'.$zone.'.';
    }

    private function mxTargetFqdn(string $target): string
    {
        $target = strtolower(trim($target));
        if ($target === '') {
            return '.';
        }
        if (str_ends_with($target, '.')) {
            return $target;
        }

        return $target.'.';
    }
}
