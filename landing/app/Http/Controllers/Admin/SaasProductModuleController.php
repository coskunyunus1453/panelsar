<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaasProductModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaasProductModuleController extends Controller
{
    public function index(): View
    {
        $modules = SaasProductModule::query()->orderBy('sort_order')->orderBy('label')->paginate(40);

        return view('admin.saas.modules.index', compact('modules'));
    }

    public function create(): View
    {
        return view('admin.saas.modules.create');
    }

    public function store(Request $request): RedirectResponse
    {
        SaasProductModule::query()->create($this->validated($request));

        return redirect()->route('admin.saas.modules.index')->with('status', 'Modül eklendi.');
    }

    public function edit(SaasProductModule $saas_product_module): View
    {
        return view('admin.saas.modules.edit', ['module' => $saas_product_module]);
    }

    public function update(Request $request, SaasProductModule $saas_product_module): RedirectResponse
    {
        $saas_product_module->update($this->validated($request, $saas_product_module->id));

        return redirect()->route('admin.saas.modules.index')->with('status', 'Modül güncellendi.');
    }

    public function destroy(SaasProductModule $saas_product_module): RedirectResponse
    {
        $saas_product_module->delete();

        return redirect()->route('admin.saas.modules.index')->with('status', 'Modül silindi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $moduleId = null): array
    {
        $request->validate([
            'key' => [
                'required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('saas_product_modules', 'key')->ignore($moduleId),
            ],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_paid' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        return [
            'key' => $request->string('key')->toString(),
            'label' => $request->string('label')->toString(),
            'description' => $request->input('description'),
            'is_paid' => $request->boolean('is_paid'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }
}
