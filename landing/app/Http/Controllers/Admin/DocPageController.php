<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocPage;
use App\Rules\RichHtmlNotEmpty;
use App\Support\SafeRichContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocPageController extends Controller
{
    public function index(): View
    {
        $q = DocPage::query()->with('parent');
        if (request()->filled('locale')) {
            $q->where('locale', request('locale'));
        }

        $pages = $q->orderBy('locale')->orderBy('parent_id')->orderBy('sort_order')->orderBy('title')->paginate(20)->withQueryString();

        return view('admin.doc-pages.index', [
            'pages' => $pages,
        ]);
    }

    public function create(): View
    {
        $locale = old('locale', config('app.locale'));

        $parents = DocPage::query()->forLocale($locale)->orderBy('title')->get(['id', 'title', 'slug']);

        return view('admin.doc-pages.create', [
            'parents' => $parents,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'parent_id' => $request->filled('parent_id') ? $request->input('parent_id') : null,
        ]);

        $locales = array_keys(config('landing.locales'));

        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in($locales)],
            'parent_id' => [
                'nullable',
                Rule::exists('doc_pages', 'id')->where('locale', $request->input('locale')),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('doc_pages', 'slug')->where('locale', $request->input('locale')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:2000'],
            'content' => ['required', 'string', new RichHtmlNotEmpty],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $data['is_published'] = $request->boolean('is_published');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $data['content'] = SafeRichContent::sanitizeStored($data['content']);

        DocPage::query()->create($data);

        return redirect()->route('admin.doc-pages.index')->with('status', 'Doküman sayfası oluşturuldu.');
    }

    public function edit(DocPage $doc_page): View
    {
        $locale = old('locale', $doc_page->locale);

        $parents = DocPage::query()
            ->forLocale($locale)
            ->where('id', '!=', $doc_page->id)
            ->orderBy('title')
            ->get(['id', 'title', 'slug']);

        return view('admin.doc-pages.edit', [
            'page' => $doc_page,
            'parents' => $parents,
        ]);
    }

    public function update(Request $request, DocPage $doc_page): RedirectResponse
    {
        $request->merge([
            'parent_id' => $request->filled('parent_id') ? $request->input('parent_id') : null,
        ]);

        $locales = array_keys(config('landing.locales'));

        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in($locales)],
            'parent_id' => [
                'nullable',
                Rule::exists('doc_pages', 'id')->where('locale', $request->input('locale')),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('doc_pages', 'slug')
                    ->where('locale', $request->input('locale'))
                    ->ignore($doc_page->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:2000'],
            'content' => ['required', 'string', new RichHtmlNotEmpty],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $data['is_published'] = $request->boolean('is_published');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        if ($data['parent_id'] !== null && (int) $data['parent_id'] === $doc_page->id) {
            return redirect()->back()->withErrors(['parent_id' => 'Sayfa kendi üst öğesi olamaz.'])->withInput();
        }

        $data['content'] = SafeRichContent::sanitizeStored($data['content']);

        $doc_page->update($data);

        return redirect()->route('admin.doc-pages.index')->with('status', 'Doküman sayfası güncellendi.');
    }

    public function destroy(DocPage $doc_page): RedirectResponse
    {
        $doc_page->delete();

        return redirect()->route('admin.doc-pages.index')->with('status', 'Doküman sayfası silindi.');
    }
}
