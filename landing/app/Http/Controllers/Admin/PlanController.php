<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::query()->orderBy('sort_order')->orderBy('name')->paginate(20);

        return view('admin.plans.index', [
            'plans' => $plans,
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.create', [
            'features_raw' => old('features_raw', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'features_raw' => ['nullable', 'string'],
        ]);

        $data = $this->validated($request);
        $data['features'] = $this->parseFeatures($request->input('features_raw'));

        Plan::query()->create($data);

        return redirect()->route('admin.plans.index')->with('status', 'Plan oluşturuldu.');
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.edit', [
            'plan' => $plan,
            'features_raw' => old('features_raw', implode("\n", $plan->features ?? [])),
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $request->validate([
            'features_raw' => ['nullable', 'string'],
        ]);

        $data = $this->validated($request, $plan->id);
        $data['features'] = $this->parseFeatures($request->input('features_raw'));

        $plan->update($data);

        return redirect()->route('admin.plans.index')->with('status', 'Plan güncellendi.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        return redirect()->route('admin.plans.index')->with('status', 'Plan silindi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:plans,slug'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'price_label' => ['required', 'string', 'max:64'],
            'price_note' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($ignoreId !== null) {
            $rules['slug'] = ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:plans,slug,'.$ignoreId];
        }

        $data = $request->validate($rules);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }

    /**
     * @return array<int, string>|null
     */
    private function parseFeatures(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $lines = array_values(array_filter(array_map('trim', $lines)));

        return $lines === [] ? null : $lines;
    }
}
