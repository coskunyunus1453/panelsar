<?php

namespace App\Support\Seo;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use App\Models\DocPage;
use Illuminate\Support\Str;

final class SchemaBuilder
{
    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    public static function breadcrumbList(array $crumbs): array
    {
        $items = [];
        foreach (array_values($crumbs) as $i => $c) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $c['name'],
                'item' => $c['url'],
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function blogPosting(
        BlogPost $post,
        string $pageUrl,
        string $brandName,
        ?string $ogImageAbsolute = null,
    ): array {
        $desc = $post->effectiveMetaDescription();

        $data = [
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'description' => Str::limit($desc, 500),
            'url' => $pageUrl,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $pageUrl,
            ],
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => $post->updated_at->toAtomString(),
            'author' => [
                '@type' => 'Organization',
                'name' => $brandName,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $brandName,
            ],
        ];

        if ($post->relationLoaded('category') && $post->category instanceof BlogCategory) {
            $data['articleSection'] = $post->category->name;
        }

        if ($ogImageAbsolute) {
            $data['image'] = [$ogImageAbsolute];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function webPageSimple(string $name, string $url, string $description, string $siteName, ?string $primaryImageUrl = null): array
    {
        $data = [
            '@type' => 'WebPage',
            'name' => $name,
            'url' => $url,
            'description' => Str::limit($description, 500),
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => url('/'),
            ],
        ];

        if ($primaryImageUrl) {
            $data['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url' => $primaryImageUrl,
            ];
        }

        return $data;
    }

    public static function techArticleDoc(DocPage $page, string $pageUrl, string $brandName): array
    {
        $desc = $page->effectiveMetaDescription();

        return [
            '@type' => 'TechArticle',
            'headline' => $page->title,
            'description' => Str::limit($desc, 500),
            'url' => $pageUrl,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $pageUrl,
            ],
            'author' => [
                '@type' => 'Organization',
                'name' => $brandName,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $brandName,
            ],
            'dateModified' => $page->updated_at->toAtomString(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    public static function graph(array $nodes): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => array_values($nodes),
        ];
    }

    public static function encode(array $schema): string
    {
        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
    }

    /**
     * @return array<string, mixed>
     */
    public static function communityQuestion(
        CommunityTopic $topic,
        string $pageUrl,
        int $answerCount,
        ?CommunityPost $acceptedAnswer = null,
    ): array {
        $plainBody = Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $topic->body))), 4000);

        $main = [
            '@type' => 'Question',
            'name' => Str::limit($topic->title, 500),
            'text' => $plainBody !== '' ? $plainBody : Str::limit($topic->title, 500),
            'answerCount' => $answerCount,
            'datePublished' => $topic->created_at?->toAtomString(),
            'author' => [
                '@type' => 'Person',
                'name' => Str::limit((string) ($topic->author?->name ?: landing_t('community.schema_anonymous_member')), 120),
            ],
        ];

        if ($acceptedAnswer instanceof CommunityPost && $acceptedAnswer->body !== '') {
            $answerPlain = Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $acceptedAnswer->body))), 4000);
            $main['acceptedAnswer'] = [
                '@type' => 'Answer',
                'text' => $answerPlain,
                'url' => $pageUrl.'#reply-'.$acceptedAnswer->getKey(),
                'datePublished' => $acceptedAnswer->created_at?->toAtomString(),
                'author' => [
                    '@type' => 'Person',
                    'name' => Str::limit((string) ($acceptedAnswer->author?->name ?: landing_t('community.schema_anonymous_member')), 120),
                ],
            ];
        }

        return [
            '@type' => 'QAPage',
            'mainEntity' => $main,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function communityArticle(CommunityTopic $topic, string $pageUrl, string $brandName): array
    {
        $plainBody = Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $topic->body))), 8000);

        return [
            '@type' => 'Article',
            'headline' => Str::limit($topic->title, 110),
            'url' => $pageUrl,
            'datePublished' => $topic->created_at?->toAtomString(),
            'dateModified' => $topic->updated_at?->toAtomString(),
            'author' => [
                '@type' => 'Person',
                'name' => Str::limit((string) ($topic->author?->name ?: landing_t('community.schema_anonymous_member')), 120),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $brandName,
            ],
            'articleBody' => $plainBody !== '' ? $plainBody : Str::limit($topic->title, 500),
        ];
    }
}
