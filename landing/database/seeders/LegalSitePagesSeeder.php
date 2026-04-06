<?php

namespace Database\Seeders;

use App\Models\SitePage;
use Illuminate\Database\Seeder;

class LegalSitePagesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (LegalPagesDefinitions::all() as $row) {
            SitePage::query()->updateOrCreate(
                [
                    'locale' => $row['locale'],
                    'slug' => $row['slug'],
                ],
                [
                    'title' => $row['title'],
                    'meta_description' => $row['meta_description'],
                    'content' => $row['content'],
                    'is_published' => true,
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }
}
