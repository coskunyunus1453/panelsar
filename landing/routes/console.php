<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('landing:publish-legacy-media', function (): int {
    $from = Storage::disk('public');
    $to = Storage::disk('landing_assets');
    if (! $from->exists('landing')) {
        $this->info('storage/app/public/landing yok; kopyalanacak dosya yok.');

        return 0;
    }
    $paths = $from->allFiles('landing');
    $n = 0;
    foreach ($paths as $path) {
        if ($to->exists($path)) {
            continue;
        }
        $to->put($path, $from->get($path));
        $this->line("Kopyalandı: {$path}");
        $n++;
    }
    $this->info($n > 0 ? "Tamam ({$n} dosya)." : 'Tüm dosyalar zaten public/landing-assets altında.');

    return 0;
})->purpose('Eski storage/app/public/landing dosyalarını public/landing-assets içine kopyalar (URL /landing-assets/... olur)');
