<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PanelsarInstallCheckCommand extends Command
{
    protected $signature = 'panelsar:install-check {--ping : Engine /health isteği dene}';

    protected $description = 'Üretim öncesi panel yapılandırmasını kontrol eder';

    public function handle(): int
    {
        $ok = true;
        $env = (string) config('app.env');
        $debug = (bool) config('app.debug');
        $key = (string) config('panelsar.engine_internal_key', '');
        $url = rtrim((string) config('panelsar.engine_url', ''), '/');

        if ($env === 'production' && $debug) {
            $this->error('APP_DEBUG üretimde false olmalı.');
            $ok = false;
        } else {
            $this->info('APP_ENV='.$env.' APP_DEBUG='.($debug ? 'true' : 'false'));
        }

        if ($key === '') {
            $this->warn('ENGINE_INTERNAL_KEY boş — engine API çağrıları başarısız olur.');
            $ok = false;
        } else {
            $this->info('ENGINE_INTERNAL_KEY tanımlı.');
        }

        if ($url === '') {
            $this->warn('ENGINE_API_URL boş.');
            $ok = false;
        } else {
            $this->info('ENGINE_API_URL='.$url);
        }

        if ($this->option('ping') && $url !== '') {
            try {
                $r = Http::timeout(5)->get($url.'/health');
                if ($r->successful()) {
                    $this->info('Engine /health: OK');
                } else {
                    $this->error('Engine /health: HTTP '.$r->status());
                    $ok = false;
                }
            } catch (\Throwable $e) {
                $this->error('Engine erişilemedi: '.$e->getMessage());
                $ok = false;
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
