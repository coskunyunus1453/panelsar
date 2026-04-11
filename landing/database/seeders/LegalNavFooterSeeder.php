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
            ['label' => 'KVKK', 'label_en' => 'Privacy notice', 'href' => '/p/kvkk'],
            ['label' => 'Gizlilik', 'label_en' => 'Privacy policy', 'href' => '/p/gizlilik-politikasi'],
            ['label' => 'Çerezler', 'label_en' => 'Cookie policy', 'href' => '/p/cerez-politikasi'],
            ['label' => 'Mesafeli satış', 'label_en' => 'Distance sales', 'href' => '/p/mesafeli-satis'],
            ['label' => 'Kullanım koşulları', 'label_en' => 'Terms of use', 'href' => '/p/kullanim-kosullari'],
            ['label' => 'SLA', 'label_en' => 'SLA', 'href' => '/p/sla'],
            ['label' => 'İade ve iptal', 'label_en' => 'Refunds', 'href' => '/p/iade-ve-iptal'],
            ['label' => 'Veri merkezi', 'label_en' => 'Data centre', 'href' => '/p/veri-merkezi'],
            ['label' => 'Müşteri sözleşmesi', 'label_en' => 'Customer agreement', 'href' => '/p/musteri-sozlesmesi'],
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
                    'label_en' => $row['label_en'],
                    'sort_order' => $start + $i,
                    'is_active' => true,
                    'open_in_new_tab' => false,
                ]
            );
        }
    }
}
