<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class SyncPanelAbilitiesCommand extends Command
{
    protected $signature = 'hostvim:sync-abilities';

    protected $description = 'config/hostvim_abilities.php içindeki yetenekleri Spatie permissions tablosuna ekler/günceller';

    public function handle(): int
    {
        $registry = config('hostvim_abilities', []);
        if (! is_array($registry)) {
            $this->error('hostvim_abilities config eksik veya geçersiz.');

            return self::FAILURE;
        }

        $guard = 'web';
        foreach ($registry as $row) {
            if (! is_array($row) || ! isset($row['name'])) {
                continue;
            }
            $name = (string) $row['name'];
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => $guard],
                []
            );
        }

        $this->info('Yetenekler senkronize edildi: '.count($registry).' kayıt.');

        return self::SUCCESS;
    }
}
