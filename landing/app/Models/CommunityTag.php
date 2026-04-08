<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class CommunityTag extends Model
{
    /**
     * @return list<string>
     */
    public static function parseNamesFromCsv(?string $raw, int $max = 5): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $parts = preg_split('/[,\n]+/u', $raw) ?: [];
        $seen = [];
        $out = [];
        foreach ($parts as $p) {
            $t = Str::limit(trim(strip_tags((string) $p)), 48);
            if ($t === '') {
                continue;
            }
            $k = mb_strtolower($t);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $t;
        }

        return array_slice($out, 0, max(0, $max));
    }

    protected $fillable = [
        'slug',
        'name',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(
            CommunityTopic::class,
            'community_tag_topic',
            'community_tag_id',
            'community_topic_id'
        )->withTimestamps();
    }

    /**
     * @param  list<string>  $names
     */
    public static function syncToTopic(CommunityTopic $topic, array $names, int $limit = 5): void
    {
        $ids = [];
        foreach (array_slice($names, 0, max(0, $limit)) as $name) {
            $slug = Str::slug($name);
            if ($slug === '') {
                continue;
            }
            $tag = static::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => Str::limit($name, 64)]
            );
            $ids[] = $tag->getKey();
        }
        $topic->tags()->sync($ids);
    }
}
