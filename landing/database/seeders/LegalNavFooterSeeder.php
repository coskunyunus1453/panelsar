<?php

namespace Database\Seeders;

use App\Models\NavMenuItem;
use Illuminate\Database\Seeder;

/**
 * Footer menüsüne yasal sayfa bağlantıları (href ile idempotent).
 */
class LegalNavFooterSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['label' => 'KVKK', 'href' => '/p/kvkk'],
            ['label' => 'Gizlilik', 'href' => '/p/gizlilik-politikasi'],
            ['label' => 'Çerezler', 'href' => '/p/cerez-politikasi'],
            ['label' => 'Mesafeli satış', 'href' => '/p/mesafeli-satis'],
            ['label' => 'Kullanım koşulları', 'href' => '/p/kullanim-kosullari'],
            ['label' => 'SLA', 'href' => '/p/sla'],
            ['label' => 'İade ve iptal', 'href' => '/p/iade-ve-iptal'],
            ['label' => 'Veri merkezi', 'href' => '/p/veri-merkezi'],
            ['label' => 'Müşteri sözleşmesi', 'href' => '/p/musteri-sozlesmesi'],
        ];

        $start = 100;
        foreach ($items as $i => $row) {
            NavMenuItem::query()->updateOrCreate(
                [
                    'zone' => NavMenuItem::ZONE_FOOTER,
                    'href' => $row['href'],
                ],
                [
                    'label' => $row['label'],
                    'sort_order' => $start + $i,
                    'is_active' => true,
                    'open_in_new_tab' => false,
                ]
            );
        }
    }
}
