<?php

namespace App\Services\Community;

use App\Models\CommunityCategory;
use App\Models\CommunityTopic;
use Illuminate\Support\Str;

class CommunitySlugService
{
    public function uniqueCategorySlug(string $base, ?int $exceptId = null): string
    {
        $slug = Str::slug($base) ?: 'category';
        $original = $slug;
        $i = 2;
        while (CommunityCategory::query()
            ->where('slug', $slug)
            ->when($exceptId !== null, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists()) {
            $slug = $original.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function uniqueTopicSlug(string $base, ?int $exceptId = null): string
    {
        $slug = Str::slug($base) ?: 'topic';
        $original = $slug;
        $i = 2;
        while (CommunityTopic::query()
            ->where('slug', $slug)
            ->when($exceptId !== null, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists()) {
            $slug = $original.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
