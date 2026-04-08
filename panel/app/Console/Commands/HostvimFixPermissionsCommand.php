<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class HostvimFixPermissionsCommand extends Command
{
    protected $signature = 'hostvim:fix-permissions';

    protected $description = 'Panel storage/bootstrap/public dizinlerini oluşturur ve mümkünse chmod uygular (chown için deploy script kullanın)';

    public function handle(): int
    {
        $panelRoot = base_path();
        $dirs = [
            storage_path('app/public'),
            storage_path('app/private'),
            storage_path('app/ssh-home'),
            storage_path('app/ssh-home/.ssh'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
            public_path('admin'),
        ];

        $ok = true;
        foreach ($dirs as $dir) {
            try {
                File::ensureDirectoryExists($dir, 0755, true);
                if (File::isDirectory($dir)) {
                    @chmod($dir, 0775);
                }
                $this->line('OK  '.$dir);
            } catch (\Throwable $e) {
                $ok = false;
                $this->error('FAIL '.$dir.' — '.$e->getMessage());
            }
        }

        $script = dirname($panelRoot).DIRECTORY_SEPARATOR.'deploy'.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'fix-panel-permissions.sh';
        if (is_file($script)) {
            $this->newLine();
            $this->comment('Web sunucusu yazabilsin diye sahiplik (bir kez, sudo):');
            $this->line('  sudo bash '.escapeshellarg($script).' '.escapeshellarg($panelRoot));
            if (\PHP_OS_FAMILY === 'Darwin' && is_dir('/Applications/XAMPP/xamppfiles')) {
                $this->line('  (XAMPP Apache genelde daemon kullanır; script bunu otomatik seçer.)');
            }
        } else {
            $this->newLine();
            $this->comment('Sahiplik için (Linux örnek): sudo chown -R www-data:www-data storage bootstrap/cache public/admin && sudo chmod -R ug+rwX storage bootstrap/cache public/admin');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
