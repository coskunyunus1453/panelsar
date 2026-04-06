<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaasCustomer;
use App\Models\SaasLicense;
use App\Models\SaasLicenseProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SaasLicenseController extends Controller
{
    public function index(): View
    {
        $licenses = SaasLicense::query()
            ->with(['customer', 'product'])
            ->orderByDesc('id')
            ->paginate(25);

        return view('admin.saas.licenses.index', compact('licenses'));
    }

    public function create(): View
    {
        return view('admin.saas.licenses.create', [
            'customers' => SaasCustomer::query()->orderBy('name')->get(),
            'products' => SaasLicenseProduct::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['license_key'] = 'hv_'.Str::lower(Str::random(32));
        SaasLicense::query()->create($data);

        return redirect()->route('admin.saas.licenses.index')->with('status', 'Lisans oluşturuldu; anahtar listede görünür.');
    }

    public function edit(SaasLicense $saas_license): View
    {
        return view('admin.saas.licenses.edit', [
            'license' => $saas_license,
            'customers' => SaasCustomer::query()->orderBy('name')->get(),
            'products' => SaasLicenseProduct::query()->orderBy('sort_order')->orderBy('name')->get(),
            'limits_override_raw' => old('limits_override_raw', json_encode($saas_license->limits_override ?? new \stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            'modules_override_raw' => old('modules_override_raw', json_encode($saas_license->modules_override ?? new \stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
        ]);
    }

    public function update(Request $request, SaasLicense $saas_license): RedirectResponse
    {
        $saas_license->update($this->validated($request));

        return redirect()->route('admin.saas.licenses.index')->with('status', 'Lisans güncellendi.');
    }

    public function destroy(SaasLicense $saas_license): RedirectResponse
    {
        $saas_license->delete();

        return redirect()->route('admin.saas.licenses.index')->with('status', 'Lisans silindi.');
    }

    public function regenerate(SaasLicense $saas_license): RedirectResponse
    {
        $saas_license->update([
            'license_key' => 'hv_'.Str::lower(Str::random(32)),
        ]);

        return redirect()->route('admin.saas.licenses.edit', $saas_license)->with('status', 'Yeni lisans anahtarı üretildi; eski anahtar geçersiz.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $request->validate([
            'saas_customer_id' => ['required', 'exists:saas_customers,id'],
            'saas_license_product_id' => ['required', 'exists:saas_license_products,id'],
            'status' => ['required', 'in:active,suspended,expired,revoked'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'subscription_status' => ['nullable', 'in:active,past_due,canceled,none'],
            'subscription_renews_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'limits_override_raw' => ['nullable', 'string', 'max:131072'],
            'modules_override_raw' => ['nullable', 'string', 'max:131072'],
        ]);

        return [
            'saas_customer_id' => (int) $request->input('saas_customer_id'),
            'saas_license_product_id' => (int) $request->input('saas_license_product_id'),
            'status' => $request->string('status')->toString(),
            'starts_at' => $request->input('starts_at'),
            'expires_at' => $request->input('expires_at'),
            'subscription_status' => $request->input('subscription_status'),
            'subscription_renews_at' => $request->input('subscription_renews_at'),
            'notes' => $request->input('notes'),
            'limits_override' => $this->jsonField($request->input('limits_override_raw')),
            'modules_override' => $this->jsonField($request->input('modules_override_raw')),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function jsonField(?string $raw): ?array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            abort(422, 'Geçersiz JSON: '.json_last_error_msg());
        }

        return $decoded;
    }
}
