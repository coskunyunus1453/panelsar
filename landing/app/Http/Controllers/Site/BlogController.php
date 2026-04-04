<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Support\Seo\SchemaBuilder;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $locale = app()->getLocale();

        $posts = BlogPost::query()
            ->published()
            ->forLocale($locale)
            ->with('category')
            ->orderByDesc('published_at')
            ->paginate(9);

        $categories = BlogCategory::query()
            ->forLocale($locale)
            ->whereHas('posts', function ($q) use ($locale): void {
                $q->published()->where('blog_posts.locale', $locale);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $schema = SchemaBuilder::graph([
            SchemaBuilder::breadcrumbList([
                ['name' => landing_p('brand.name'), 'url' => url('/')],
                ['name' => landing_t('blog.heading'), 'url' => route('blog.index', absolute: true)],
            ]),
        ]);

        return view('site.blog.index', [
            'posts' => $posts,
            'categories' => $categories,
            'seoSchema' => SchemaBuilder::encode($schema),
        ]);
    }

    public function category(BlogCategory $blog_category): View
    {
        $locale = app()->getLocale();

        $posts = BlogPost::query()
            ->published()
            ->forLocale($locale)
            ->where('blog_category_id', $blog_category->id)
            ->orderByDesc('published_at')
            ->paginate(9);

        $categories = BlogCategory::query()
            ->forLocale($locale)
            ->whereHas('posts', function ($q) use ($locale): void {
                $q->published()->where('blog_posts.locale', $locale);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $canonical = route('blog.category', $blog_category->slug, absolute: true);
        $desc = $blog_category->listingDescription() ?: landing_t('blog.meta_description');

        $schema = SchemaBuilder::graph([
            SchemaBuilder::breadcrumbList([
                ['name' => landing_p('brand.name'), 'url' => url('/')],
                ['name' => landing_t('blog.heading'), 'url' => route('blog.index', absolute: true)],
                ['name' => $blog_category->name, 'url' => $canonical],
            ]),
        ]);

        return view('site.blog.category', [
            'category' => $blog_category,
            'posts' => $posts,
            'categories' => $categories,
            'seoSchema' => SchemaBuilder::encode($schema),
            'seoCanonical' => $canonical,
            'seoDescription' => $desc,
        ]);
    }

    public function show(string $slug): View
    {
        $post = BlogPost::query()
            ->published()
            ->forLocale(app()->getLocale())
            ->where('slug', $slug)
            ->with('category')
            ->firstOrFail();

        $canonical = $post->canonicalAbsoluteUrl();
        $brand = landing_p('brand.name');
        $ogImage = $post->ogImageAbsolute();

        $breadcrumbs = [
            ['name' => $brand, 'url' => url('/')],
            ['name' => landing_t('blog.heading'), 'url' => route('blog.index', absolute: true)],
        ];
        if ($post->category) {
            $breadcrumbs[] = [
                'name' => $post->category->name,
                'url' => route('blog.category', $post->category->slug, absolute: true),
            ];
        }
        $breadcrumbs[] = ['name' => $post->title, 'url' => $canonical];

        $schema = SchemaBuilder::graph([
            SchemaBuilder::blogPosting($post, $canonical, $brand, $ogImage),
            SchemaBuilder::breadcrumbList($breadcrumbs),
        ]);

        return view('site.blog.show', [
            'post' => $post,
            'seoCanonical' => $canonical,
            'seoDescription' => $post->effectiveMetaDescription(),
            'seoOgImage' => $ogImage,
            'seoSchema' => SchemaBuilder::encode($schema),
            'seoRobots' => $post->robots,
        ]);
    }
}
