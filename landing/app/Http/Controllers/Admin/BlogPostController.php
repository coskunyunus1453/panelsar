<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Rules\RichHtmlNotEmpty;
use App\Support\SafeRichContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BlogPostController extends Controller
{
    public function index(): View
    {
        $q = BlogPost::query()->with('category');
        if (request()->filled('locale')) {
            $q->where('locale', request('locale'));
        }

        $posts = $q->orderBy('locale')->orderByDesc('published_at')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('admin.blog-posts.index', [
            'posts' => $posts,
        ]);
    }

    public function create(): View
    {
        $locale = old('locale', config('app.locale'));

        return view('admin.blog-posts.create', [
            'categories' => BlogCategory::query()->forLocale($locale)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_published'] = $request->boolean('is_published');

        if ($data['is_published'] && ($data['published_at'] ?? null) === null) {
            $data['published_at'] = now();
        }

        $data['content'] = SafeRichContent::sanitizeStored($data['content']);

        BlogPost::query()->create($data);

        return redirect()->route('admin.blog-posts.index')->with('status', 'Yazı oluşturuldu.');
    }

    public function edit(BlogPost $blog_post): View
    {
        $locale = old('locale', $blog_post->locale);

        return view('admin.blog-posts.edit', [
            'post' => $blog_post,
            'categories' => BlogCategory::query()->forLocale($locale)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, BlogPost $blog_post): RedirectResponse
    {
        $data = $this->validated($request, $blog_post);
        $data['is_published'] = $request->boolean('is_published');

        if ($data['is_published'] && ($data['published_at'] ?? null) === null) {
            $data['published_at'] = now();
        }

        $data['content'] = SafeRichContent::sanitizeStored($data['content']);

        $blog_post->update($data);

        return redirect()->route('admin.blog-posts.index')->with('status', 'Yazı güncellendi.');
    }

    public function destroy(BlogPost $blog_post): RedirectResponse
    {
        $blog_post->delete();

        return redirect()->route('admin.blog-posts.index')->with('status', 'Yazı silindi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?BlogPost $existing = null): array
    {
        $locales = array_keys(config('landing.locales'));

        $slugRule = Rule::unique('blog_posts', 'slug')->where('locale', $request->input('locale'));
        if ($existing) {
            $slugRule->ignore($existing->id);
        }

        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in($locales)],
            'blog_category_id' => [
                'nullable',
                Rule::exists('blog_categories', 'id')->where('locale', $request->input('locale')),
            ],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slugRule],
            'title' => ['required', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:2000'],
            'canonical_url' => ['nullable', 'string', 'max:2048', $this->optionalHrefRule()],
            'og_image' => ['nullable', 'string', 'max:2048', $this->optionalOgImageRule()],
            'robots' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9,\s\-]+$/'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string', new RichHtmlNotEmpty],
            'published_at' => ['nullable', 'date'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $data['blog_category_id'] = $data['blog_category_id'] ?: null;
        $data['canonical_url'] = isset($data['canonical_url']) && trim((string) $data['canonical_url']) !== '' ? trim($data['canonical_url']) : null;
        $data['og_image'] = isset($data['og_image']) && trim((string) $data['og_image']) !== '' ? trim($data['og_image']) : null;
        $data['robots'] = isset($data['robots']) && trim((string) $data['robots']) !== '' ? trim($data['robots']) : null;

        return $data;
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
