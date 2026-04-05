<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SaasLicenseValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseValidateController extends Controller
{
    public function __invoke(Request $request, SaasLicenseValidationService $saas): JsonResponse
    {
        $secret = (string) config('hostvim_saas.license_api_secret', '');

        if ($secret !== '') {
            $bearer = $request->bearerToken();
            if ($bearer !== $secret) {
                return response()->json(['valid' => false, 'code' => 'unauthorized', 'message' => 'Invalid API token'], 401);
            }
        }

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:128'],
        ]);

        $payload = $saas->validateKey($validated['key']);

        // Her zaman 200 + gövdede valid — panel Http client kolayca parse eder
        return response()->json($payload);
    }
}
