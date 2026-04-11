<?php

namespace App\Models;

use App\Support\SafeRichContent;
use App\Support\Seo\SeoUrls;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    protected $fillable = [
        'locale',
        'blog_category_id',
        'slug',
        'title',
        'meta_title',
        'meta_description',
        'canonical_url',
        'og_image',
        'robots',
        'excerpt',
        'content',
        'published_at',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
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
        if ($this->excerpt) {
            return Str::limit(strip_tags($this->excerpt), 320);
        }

        return Str::limit(strip_tags(SafeRichContent::toHtml($this->content)), 160);
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

        return route('blog.show', $this->slug, absolute: true);
    }

    /** Harici tam URL değilse ?lang= ile çok dilli canonical. */
    public function seoCanonicalAbsoluteUrl(): string
    {
        $raw = trim((string) $this->canonical_url);
        if ($raw !== '' && preg_match('#^https?://#i', $raw)) {
            return $this->canonicalAbsoluteUrl();
        }

        return landing_url_with_lang(route('blog.show', $this->slug, absolute: true), (string) $this->locale);
    }

    public function ogImageAbsolute(): ?string
    {
        return SeoUrls::absolute($this->og_image);
    }
}
