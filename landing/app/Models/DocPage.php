<?php

namespace App\Models;

use App\Support\SafeRichContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DocPage extends Model
{
    protected $fillable = [
        'locale',
        'parent_id',
        'slug',
        'title',
        'meta_title',
        'meta_description',
        'content',
        'sort_order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DocPage::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(DocPage::class, 'parent_id')->orderBy('sort_order');
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
}
