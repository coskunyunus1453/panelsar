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
            ['label' => 'Özellikler', 'href' => '/#features'],
            ['label' => 'Fiyatlandırma', 'href' => '/pricing'],
            ['label' => 'Kurulum', 'href' => '/setup'],
            ['label' => 'Dokümantasyon', 'href' => '/docs'],
            ['label' => 'Blog', 'href' => '/blog'],
            ['label' => 'SSS', 'href' => '/#faq'],
        ];

        foreach ($header as $i => $row) {
            NavMenuItem::query()->create([
                'zone' => NavMenuItem::ZONE_HEADER,
                'label' => $row['label'],
                'href' => $row['href'],
                'sort_order' => $i,
                'is_active' => true,
                'open_in_new_tab' => false,
            ]);
        }

        $footer = [
            ['label' => 'Dokümantasyon', 'href' => '/docs'],
            ['label' => 'Blog', 'href' => '/blog'],
            ['label' => 'SSS', 'href' => '/#faq'],
            ['label' => 'Yönetim girişi', 'href' => '/admin/login'],
        ];

        foreach ($footer as $i => $row) {
            NavMenuItem::query()->create([
                'zone' => NavMenuItem::ZONE_FOOTER,
                'label' => $row['label'],
                'href' => $row['href'],
                'sort_order' => $i,
                'is_active' => true,
                'open_in_new_tab' => false,
            ]);
        }
    }
}
