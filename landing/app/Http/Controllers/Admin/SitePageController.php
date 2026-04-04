<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SitePage;
use App\Rules\RichHtmlNotEmpty;
use App\Support\SafeRichContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SitePageController extends Controller
{
    public function index(): View
    {
        $q = SitePage::query();
        if (request()->filled('locale')) {
            $q->where('locale', request('locale'));
        }

        $pages = $q->orderBy('locale')->orderBy('sort_order')->orderBy('title')->paginate(20)->withQueryString();

        return view('admin.site-pages.index', [
            'pages' => $pages,
        ]);
    }

    public function create(): View
    {
        return view('admin.site-pages.create');
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
                Rule::unique('site_pages', 'slug')->where('locale', $request->input('locale')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:2000'],
            'canonical_url' => ['nullable', 'string', 'max:2048', $this->optionalHrefRule()],
            'og_image' => ['nullable', 'string', 'max:2048', $this->optionalOgImageRule()],
            'robots' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9,\s\-]+$/'],
            'content' => ['required', 'string', new RichHtmlNotEmpty],
            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['is_published'] = $request->boolean('is_published');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['canonical_url'] = isset($data['canonical_url']) && trim((string) $data['canonical_url']) !== '' ? trim($data['canonical_url']) : null;
        $data['og_image'] = isset($data['og_image']) && trim((string) $data['og_image']) !== '' ? trim($data['og_image']) : null;
        $data['robots'] = isset($data['robots']) && trim((string) $data['robots']) !== '' ? trim($data['robots']) : null;

        $data['content'] = SafeRichContent::sanitizeStored($data['content']);

        SitePage::query()->create($data);

        return redirect()->route('admin.site-pages.index')->with('status', 'Sayfa oluşturuldu.');
    }

    public function edit(SitePage $site_page): View
    {
        return view('admin.site-pages.edit', [
            'page' => $site_page,
        ]);
    }

    public function update(Request $request, SitePage $site_page): RedirectResponse
    {
        $locales = array_keys(config('landing.locales'));

        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in($locales)],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('site_pages', 'slug')
                    ->where('locale', $request->input('locale'))
                    ->ignore($site_page->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:2000'],
            'canonical_url' => ['nullable', 'string', 'max:2048', $this->optionalHrefRule()],
            'og_image' => ['nullable', 'string', 'max:2048', $this->optionalOgImageRule()],
            'robots' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9,\s\-]+$/'],
            'content' => ['required', 'string', new RichHtmlNotEmpty],
            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['is_published'] = $request->boolean('is_published');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['canonical_url'] = isset($data['canonical_url']) && trim((string) $data['canonical_url']) !== '' ? trim($data['canonical_url']) : null;
        $data['og_image'] = isset($data['og_image']) && trim((string) $data['og_image']) !== '' ? trim($data['og_image']) : null;
        $data['robots'] = isset($data['robots']) && trim((string) $data['robots']) !== '' ? trim($data['robots']) : null;

        $data['content'] = SafeRichContent::sanitizeStored($data['content']);

        $site_page->update($data);

        return redirect()->route('admin.site-pages.index')->with('status', 'Sayfa güncellendi.');
    }

    public function destroy(SitePage $site_page): RedirectResponse
    {
        $site_page->delete();

        return redirect()->route('admin.site-pages.index')->with('status', 'Sayfa silindi.');
    }

    private function optionalHrefRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }
            $v = trim($value);
            if (preg_match('#^https?://#i', $v)) {
                if (filter_var($v, FILTER_VALIDATE_URL) === false) {
                    $fail('Geçerli bir tam kanonik adres girin.');
                }

                return;
            }
            if (! str_starts_with($v, '/')) {
                $fail('Kanonik adres / veya https:// ile başlamalıdır.');
            }
            if (str_contains($v, '..')) {
                $fail('Geçersiz kanonik yol.');
            }
        };
    }

    private function optionalOgImageRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }
            $v = trim($value);
            if (preg_match('#^https?://#i', $v)) {
                if (filter_var($v, FILTER_VALIDATE_URL) === false) {
                    $fail('Geçerli bir görsel adresi girin.');
                }

                return;
            }
            if (! str_starts_with($v, '/')) {
                $fail('Görsel yolu / veya https:// ile başlamalıdır.');
            }
            if (str_contains($v, '..')) {
                $fail('Geçersiz görsel yolu.');
            }
        };
    }
}
