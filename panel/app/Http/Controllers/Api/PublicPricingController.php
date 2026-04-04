<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HostingPackage;
use Illuminate\Http\JsonResponse;

class PublicPricingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $packages = HostingPackage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['packages' => $packages]);
    }
}
