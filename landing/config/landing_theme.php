<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ön yüz temaları (Tema 1 = orange, Tema 2 = turkuaz, Tema 3 = neon/material)
    |--------------------------------------------------------------------------
    */
    'themes' => [
        'orange' => [
            'label' => 'Tema 1 — Turuncu (varsayılan)',
            'description' => 'Mevcut Hostvim turuncu vurgulu arayüz.',
        ],
        'turquoise' => [
            'label' => 'Tema 2 — Turkuaz',
            'description' => 'Beyaz ve turkuaz tonları; gece modunda turkuaz vurgular.',
        ],
        'neon' => [
            'label' => 'Tema 3 — Neon / Material (sade)',
            'description' => 'Ayrı üst-alt çerçeve, neon vurgulu material yüzeyler, gece modu. Ana sayfa üst alan + 5’li liste + 6’lı ızgara admin’den düzenlenir.',
        ],
    ],

    'graphic_motifs' => [
        'grid' => 'Izgara (varsayılan)',
        'waves' => 'Yumuşak dalgalar',
        'dots' => 'Nokta deseni',
        'radial' => 'Işıl ışın',
        'none' => 'Yok',
    ],

    'feature_icons' => [
        'layers' => 'Katmanlar / stack',
        'database' => 'Veritabanı',
        'shield' => 'Güvenlik',
        'terminal' => 'Terminal',
        'users' => 'Kullanıcılar',
        'rocket' => 'Hız / lansman',
        'globe' => 'Ağ / domain',
        'cpu' => 'Sunucu / CPU',
    ],
];
