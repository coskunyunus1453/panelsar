<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaasCustomer;
use App\Models\SaasLicense;
use App\Models\SaasLicenseProduct;
use App\Models\SaasProductModule;
use Illuminate\View\View;

class SaasDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.saas.dashboard', [
            'customers' => SaasCustomer::query()->count(),
            'licenses_active' => SaasLicense::query()->where('status', 'active')->count(),
            'products' => SaasLicenseProduct::query()->where('is_active', true)->count(),
            'modules' => SaasProductModule::query()->where('is_active', true)->count(),
            'api_endpoint' => url('/api/v1/license/validate'),
        ]);
    }
}
