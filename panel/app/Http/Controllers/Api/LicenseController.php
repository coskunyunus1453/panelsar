<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function __construct(
        private EngineApiService $engine,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $key = config('panelsar.license_key') ?: $request->query('key', '');

        return response()->json([
            'local_key_set' => (bool) config('panelsar.license_key'),
            'engine' => $key ? $this->engine->validateLicense((string) $key) : null,
        ]);
    }

    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate(['key' => 'required|string']);

        return response()->json($this->engine->validateLicense($validated['key']));
    }
}
