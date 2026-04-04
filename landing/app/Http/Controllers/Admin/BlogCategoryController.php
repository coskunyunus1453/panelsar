<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BlogCategoryController extends Controller
{
    public function index(): View
    {
        $q = BlogCategory::query();
        if (request()->filled('locale')) {
            $q->where('locale', request('locale'));
        }

        $categories = $q->orderBy('locale')->orderBy('sort_order')->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.blog-categories.index', ['categories' => $categories]);
    }

    public function create(): View
    {
        return view('admin.blog-categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $locales = array_keys(config('landing.locales'));

        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in($locales)],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_categories', 'slug')->where('locale', $request->input('locale')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:500'],
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        BlogCategory::query()->create($data);

        return redirect()->route('admin.blog-categories.index')->with('status', 'Kategori oluşturuldu.');
    }

    public function edit(BlogCategory $blog_category): View
    {
        return view('admin.blog-categories.edit', ['category' => $blog_category]);
    }

    public function update(Request $request, BlogCategory $blog_category): RedirectResponse
    {
        $locales = array_keys(config('landing.locales'));

        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in($locales)],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_categories', 'slug')
                    ->where('locale', $request->input('locale'))
                    ->ignore($blog_category->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:500'],
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $blog_category->update($data);

        return redirect()->route('admin.blog-categories.index')->with('status', 'Kategori güncellendi.');
    }

    public function destroy(BlogCategory $blog_category): RedirectResponse
    {
        $blog_category->delete();

        return redirect()->route('admin.blog-categories.index')->with('status', 'Kategori silindi.');
    }
}
