<?php

namespace Database\Seeders;

use App\Models\CmsItem;
use Illuminate\Database\Seeder;

class CmsDeploymentDocSeeder extends Seeder
{
    public function run(): void
    {
        $path = dirname(base_path()).'/docs/DEPLOYMENT.md';
        if (! is_readable($path)) {
            return;
        }

        $body = file_get_contents($path);
        if ($body === false || $body === '') {
            return;
        }

        CmsItem::query()->updateOrCreate(
            [
                'kind' => CmsItem::KIND_DOC,
                'slug' => 'deployment',
                'locale' => 'en',
            ],
            [
                'title' => 'Production deployment',
                'excerpt' => 'Hostvim panel, engine, and frontend deployment overview.',
                'body_markdown' => $body,
                'section' => 'Operations',
                'featured' => true,
                'meta_title' => 'Deployment',
                'meta_description' => 'Hostvim production deployment checklist.',
                'status' => 'published',
                'published_at' => now(),
                'is_published' => true,
            ]
        );
    }
}
