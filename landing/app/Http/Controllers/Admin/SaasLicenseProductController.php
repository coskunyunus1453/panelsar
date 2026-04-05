<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaasLicenseProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaasLicenseProductController extends Controller
{
    public function index(): View
    {
        $products = SaasLicenseProduct::query()->orderBy('sort_order')->orderBy('name')->paginate(25);

        return view('admin.saas.products.index', compact('products'));
    }

    public function create(): View
    {
        return view('admin.saas.products.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $product = SaasLicenseProduct::query()->create($this->validated($request));

        return redirect()->route('admin.saas.products.index')->with('status', 'Ürün oluşturuldu.');
    }

    public function edit(SaasLicenseProduct $saas_license_product): View
    {
        return view('admin.saas.products.edit', [
            'product' => $saas_license_product,
            'default_limits_raw' => old('default_limits_raw', json_encode($saas_license_product->default_limits ?? new \stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            'default_modules_raw' => old('default_modules_raw', json_encode($saas_license_product->default_modules ?? new \stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
        ]);
    }

    public function update(Request $request, SaasLicenseProduct $saas_license_product): RedirectResponse
    {
        $saas_license_product->update($this->validated($request, $saas_license_product->id));

        return redirect()->route('admin.saas.products.index')->with('status', 'Ürün güncellendi.');
    }

    public function destroy(SaasLicenseProduct $saas_license_product): RedirectResponse
    {
        if ($saas_license_product->licenses()->exists()) {
            return redirect()->route('admin.saas.products.index')->with('error', 'Bu ürüne bağlı lisans varken silinemez.');
        }
        $saas_license_product->delete();

        return redirect()->route('admin.saas.products.index')->with('status', 'Ürün silindi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $productId = null): array
    {
        $request->validate([
            'code' => [
                'required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('saas_license_products', 'code')->ignore($productId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'default_limits_raw' => ['nullable', 'string'],
            'default_modules_raw' => ['nullable', 'string'],
        ]);

        return [
            'code' => $request->string('code')->toString(),
            'name' => $request->string('name')->toString(),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'default_limits' => $this->jsonField($request->input('default_limits_raw')),
            'default_modules' => $this->jsonField($request->input('default_modules_raw')),
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
