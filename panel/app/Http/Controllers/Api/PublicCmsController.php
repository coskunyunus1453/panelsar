<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCmsController extends Controller
{
    private function locale(Request $request): string
    {
        $loc = app()->getLocale();
        if (! is_string($loc) || $loc === '') {
            return 'en';
        }

        return substr($loc, 0, 16);
    }

    private function fallbackLocale(): string
    {
        return 'en';
    }

    public function landing(Request $request): JsonResponse
    {
        $locale = $this->locale($request);
        $page = $this->findPublishedSingleton(CmsItem::KIND_LANDING, $locale);

        return response()->json(['page' => $page]);
    }

    public function install(Request $request): JsonResponse
    {
        $locale = $this->locale($request);
        $page = $this->findPublishedSingleton(CmsItem::KIND_INSTALL, $locale);

        return response()->json(['page' => $page]);
    }

    public function docsIndex(Request $request): JsonResponse
    {
        $locale = $this->locale($request);
        $items = CmsItem::query()
            ->where('kind', CmsItem::KIND_DOC)
            ->where('locale', $locale)
            ->where('status', 'published')
            ->where('is_published', true)
            ->orderByDesc('featured')
            ->orderBy('section')
            ->orderBy('title')
            ->get(['id', 'kind', 'slug', 'locale', 'title', 'excerpt', 'section', 'featured', 'published_at']);

        if ($items->isEmpty() && $locale !== $this->fallbackLocale()) {
            $items = CmsItem::query()
                ->where('kind', CmsItem::KIND_DOC)
                ->where('locale', $this->fallbackLocale())
                ->where('status', 'published')
                ->where('is_published', true)
                ->orderByDesc('featured')
                ->orderBy('section')
                ->orderBy('title')
                ->get(['id', 'kind', 'slug', 'locale', 'title', 'excerpt', 'section', 'featured', 'published_at']);
        }

        return response()->json(['items' => $items]);
    }

    public function docsShow(Request $request, string $slug): JsonResponse
    {
        $locale = $this->locale($request);
        $page = $this->findPublishedSlug(CmsItem::KIND_DOC, $slug, $locale);
        abort_if($page === null, 404);

        return response()->json(['page' => $page]);
    }

    public function blogIndex(Request $request): JsonResponse
    {
        $locale = $this->locale($request);
        $items = CmsItem::query()
            ->where('kind', CmsItem::KIND_BLOG)
            ->where('locale', $locale)
            ->where('status', 'published')
            ->where('is_published', true)
            ->orderByDesc('featured')
            ->orderByDesc('published_at')
            ->orderBy('title')
            ->get(['id', 'kind', 'slug', 'locale', 'title', 'excerpt', 'section', 'featured', 'published_at']);

        if ($items->isEmpty() && $locale !== $this->fallbackLocale()) {
            $items = CmsItem::query()
                ->where('kind', CmsItem::KIND_BLOG)
                ->where('locale', $this->fallbackLocale())
                ->where('status', 'published')
                ->where('is_published', true)
                ->orderByDesc('featured')
                ->orderByDesc('published_at')
                ->orderBy('title')
                ->get(['id', 'kind', 'slug', 'locale', 'title', 'excerpt', 'section', 'featured', 'published_at']);
        }

        return response()->json(['items' => $items]);
    }

    public function blogShow(Request $request, string $slug): JsonResponse
    {
        $locale = $this->locale($request);
        $page = $this->findPublishedSlug(CmsItem::KIND_BLOG, $slug, $locale);
        abort_if($page === null, 404);

        return response()->json(['page' => $page]);
    }

    private function findPublishedSingleton(string $kind, string $locale): ?CmsItem
    {
        $page = CmsItem::query()
            ->where('kind', $kind)
            ->where('locale', $locale)
            ->where('slug', '')
            ->where('status', 'published')
            ->where('is_published', true)
            ->first();

        if ($page === null && $locale !== $this->fallbackLocale()) {
            $page = CmsItem::query()
                ->where('kind', $kind)
                ->where('locale', $this->fallbackLocale())
                ->where('slug', '')
                ->where('status', 'published')
                ->where('is_published', true)
                ->first();
        }

        return $page;
    }

    private function findPublishedSlug(string $kind, string $slug, string $locale): ?CmsItem
    {
        $page = CmsItem::query()
            ->where('kind', $kind)
            ->where('slug', $slug)
            ->where('locale', $locale)
            ->where('status', 'published')
            ->where('is_published', true)
            ->first();

        if ($page === null && $locale !== $this->fallbackLocale()) {
            $page = CmsItem::query()
                ->where('kind', $kind)
                ->where('slug', $slug)
                ->where('locale', $this->fallbackLocale())
                ->where('status', 'published')
                ->where('is_published', true)
                ->first();
        }

        return $page;
    }
}
