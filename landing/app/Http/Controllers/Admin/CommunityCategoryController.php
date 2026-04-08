<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityCategory;
use App\Services\Community\CommunitySlugService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityCategoryController extends Controller
{
    public function __construct(
        private CommunitySlugService $slugs,
    ) {}

    public function index(): View
    {
        $categories = CommunityCategory::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.community.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.community.categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null, true);
        $slugBase = ! empty($data['slug']) ? (string) $data['slug'] : (string) $data['name'];
        $data['slug'] = $this->slugs->uniqueCategorySlug($slugBase);
        $data['is_active'] = $request->boolean('is_active');
        CommunityCategory::query()->create($data);

        return redirect()->route('admin.community.categories.index')->with('status', 'Kategori oluşturuldu.');
    }

    public function edit(CommunityCategory $community_category): View
    {
        return view('admin.community.categories.edit', ['category' => $community_category]);
    }

    public function update(Request $request, CommunityCategory $community_category): RedirectResponse
    {
        $data = $this->validated($request, $community_category, false);
        if (isset($data['slug'])) {
            $data['slug'] = $this->slugs->uniqueCategorySlug($data['slug'], (int) $community_category->getKey());
        }
        $data['is_active'] = $request->boolean('is_active');
        $community_category->update($data);

        return redirect()->route('admin.community.categories.index')->with('status', 'Kategori güncellendi.');
    }

    public function destroy(CommunityCategory $community_category): RedirectResponse
    {
        if ($community_category->topics()->exists()) {
            return redirect()->route('admin.community.categories.index')->with('error', 'Bu kategoride konular var; önce taşıyın veya silin.');
        }
        $community_category->delete();

        return redirect()->route('admin.community.categories.index')->with('status', 'Kategori silindi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CommunityCategory $existing = null, bool $isCreate = false): array
    {
        $slugRule = 'nullable|string|max:191';
        if (! $isCreate && $existing) {
            $slugRule .= '|unique:community_categories,slug,'.$existing->getKey();
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'slug' => $slugRule,
            'description' => 'nullable|string|max:20000',
            'sort_order' => 'nullable|integer|min:0|max:999999',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:2000',
            'robots_override' => 'nullable|in:index,noindex',
        ]);
    }
}
