<?php

namespace Database\Seeders;

use App\Models\NavMenuItem;
use Illuminate\Database\Seeder;

class NavMenuSeeder extends Seeder
{
    public function run(): void
    {
        if (NavMenuItem::query()->exists()) {
            return;
        }

        $header = [
            ['label' => 'Özellikler', 'label_en' => 'Features', 'href' => '/#features'],
            ['label' => 'Fiyatlandırma', 'label_en' => 'Pricing', 'href' => '/pricing'],
            ['label' => 'Kurulum', 'label_en' => 'Installation', 'href' => '/setup'],
            ['label' => 'Dokümantasyon', 'label_en' => 'Documentation', 'href' => '/docs'],
            ['label' => 'Blog', 'label_en' => 'Blog', 'href' => '/blog'],
            ['label' => 'SSS', 'label_en' => 'FAQ', 'href' => '/#faq'],
        ];

        foreach ($header as $i => $row) {
            NavMenuItem::query()->create([
                'zone' => NavMenuItem::ZONE_HEADER,
                'label' => $row['label'],
                'label_en' => $row['label_en'],
                'href' => $row['href'],
                'sort_order' => $i,
                'is_active' => true,
                'open_in_new_tab' => false,
            ]);
        }

        $footer = [
            ['label' => 'Dokümantasyon', 'label_en' => 'Documentation', 'href' => '/docs'],
            ['label' => 'Blog', 'label_en' => 'Blog', 'href' => '/blog'],
            ['label' => 'SSS', 'label_en' => 'FAQ', 'href' => '/#faq'],
            ['label' => 'Yönetim girişi', 'label_en' => 'Admin login', 'href' => '/admin/login'],
        ];

        foreach ($footer as $i => $row) {
            NavMenuItem::query()->create([
                'zone' => NavMenuItem::ZONE_FOOTER,
                'label' => $row['label'],
                'label_en' => $row['label_en'],
                'href' => $row['href'],
                'sort_order' => $i,
                'is_active' => true,
                'open_in_new_tab' => false,
            ]);
        }
    }
}
