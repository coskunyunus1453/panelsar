<?php

namespace App\Models;

use App\Support\SafeRichContent;
use App\Support\Seo\SeoUrls;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SitePage extends Model
{
    protected $fillable = [
        'locale',
        'slug',
        'title',
        'meta_title',
        'canonical_url',
        'og_image',
        'robots',
        'content',
        'meta_description',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeForLocale(Builder $query, ?string $locale = null): Builder
    {
        return $query->where('locale', $locale ?? app()->getLocale());
    }

    public function effectiveMetaTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function effectiveMetaDescription(): string
    {
        if ($this->meta_description) {
            return Str::limit(strip_tags($this->meta_description), 320);
        }

        return Str::limit(strip_tags(SafeRichContent::toHtml($this->content)), 160);
    }

    public function defaultCanonicalUrl(): string
    {
        if ($this->slug === 'setup') {
            return route('site.setup', absolute: true);
        }

        return route('site.page', $this->slug, absolute: true);
    }

    public function canonicalAbsoluteUrl(): string
    {
        if ($this->canonical_url) {
            $c = trim($this->canonical_url);
            if (preg_match('#^https?://#i', $c)) {
                return $c;
            }
            if (str_starts_with($c, '/')) {
                return url($c);
            }

            return url('/'.$c);
        }

        return $this->defaultCanonicalUrl();
    }

    public function ogImageAbsolute(): ?string
    {
        return SeoUrls::absolute($this->og_image);
    }

    public function publicPath(): string
    {
        return $this->slug === 'setup' ? '/setup' : '/p/'.$this->slug;
    }
}
