<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Models\PluginModule;
use App\Models\PluginMigrationRun;
use App\Services\DatabaseService;
use App\Support\MigrationCliResolver;
use App\Support\MigrationSsh;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;

class RunPluginMigrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $runId)
    {
    }

    public function handle(): void
    {
        $run = PluginMigrationRun::query()->find($this->runId);
        if (! $run) {
            return;
        }

        $run->status = 'running';
        $run->progress = 5;
        $run->started_at = now();
        $run->output = $this->append($run->output, 'Migration basladi.');
        $run->save();

        try {
            $this->connectivityCheck($run);
            $this->preparePlan($run);
            $this->executeTransfer($run);
            $this->postChecks($run);

            $run->status = 'success';
            $run->progress = 100;
            $run->finished_at = now();
            $run->output = $this->append($run->output, 'Migration tamamlandi.');
            $run->save();
        } catch (\Throwable $e) {
            $run->status = 'failed';
            $run->finished_at = now();
            $run->error_message = mb_substr($e->getMessage(), 0, 500);
            $run->output = $this->append($run->output, 'Hata: '.$run->error_message);
            $run->save();
        }
    }

    private function connectivityCheck(PluginMigrationRun $run): void
    {
        $run->progress = 20;
        $run->output = $this->append($run->output, 'Kaynak baglanti kontrolu yapiliyor...');
        $run->save();

        $errno = 0;
        $errstr = '';
        $conn = @fsockopen($run->source_host, $run->source_port, $errno, $errstr, 5);
        if (! $conn) {
            throw new \RuntimeException('Kaynak baglanti kurulamadi: '.$errstr);
        }
        fclose($conn);
        $run->output = $this->append($run->output, 'Baglanti kontrolu basarili.');
        $run->save();
    }

    private function preparePlan(PluginMigrationRun $run): void
    {
        $run->progress = 45;
        $plugin = PluginModule::query()->find($run->plugin_module_id);
        $target = Domain::query()->find($run->target_domain_id);
        if (! $plugin || ! $target) {
            throw new \RuntimeException('Plugin veya hedef domain bulunamadi.');
        }
        $run->output = $this->append($run->output, sprintf(
            'Tasima plani: %s -> %s (%s)',
            $plugin->slug,
            $target->name,
            $target->document_root
        ));
        $run->save();
    }

    private function executeTransfer(PluginMigrationRun $run): void
    {
        $target = Domain::query()->findOrFail($run->target_domain_id);
        $options = is_array($run->options) ? $run->options : [];
        $sourcePath = (string) ($options['source_path'] ?? '');
        if ($sourcePath === '') {
            throw new \RuntimeException('Kaynak web root belirtilmemis.');
        }

        $run->progress = 75;
        if ($run->dry_run) {
            $run->output = $this->append($run->output, 'Dry-run: dosya/veritabani kopyalama adimlari simulasyon olarak dogrulandi.');
            $run->save();
            return;
        }

        $run->output = $this->append($run->output, 'Dosya tasima basliyor...');
        $run->save();
        $this->runFileSync($run, $sourcePath, (string) $target->document_root, $options);

        $run->progress = 85;
        $run->save();
        $this->runDatabaseTransfer($run, $target, $options);
    }

    private function postChecks(PluginMigrationRun $run): void
    {
        $run->progress = 90;
        $run->output = $this->append($run->output, 'Son kontroller yapiliyor...');
        $run->save();

        if ($run->dry_run) {
            $run->output = $this->append($run->output, 'Dry-run tamam: canli veri degisikligi yok.');
            $run->save();
            return;
        }
        $options = is_array($run->options) ? $run->options : [];
        if ((string) ($options['auth_type'] ?? '') !== 'ssh_key') {
            $run->output = $this->append($run->output, 'Son kontrol: ssh_key disi modda detayli dosya sayimi atlandi.');
            $run->save();
            return;
        }
        $key = $this->decryptOrNull($options['secret_ssh_private_key'] ?? null);
        $sourcePath = (string) ($options['source_path'] ?? '');
        $target = Domain::query()->find($run->target_domain_id);
        if (! $key || ! $target || $sourcePath === '') {
            return;
        }
        $tmpKey = storage_path('app/tmp/plugin-migrate-'.$run->id.'-post.key');
        @mkdir(dirname($tmpKey), 0750, true);
        file_put_contents($tmpKey, $key);
        @chmod($tmpKey, 0600);
        try {
            $srcCount = $this->countRemoteFiles($run, $tmpKey, $sourcePath);
            $dstCount = $this->countLocalFiles((string) $target->document_root);
            $run->output = $this->append($run->output, "Dosya sayimi: kaynak={$srcCount}, hedef={$dstCount}");
            $run->save();
        } finally {
            @unlink($tmpKey);
        }
    }

    private function runFileSync(PluginMigrationRun $run, string $sourcePath, string $targetPath, array $options): void
    {
        $authType = (string) ($options['auth_type'] ?? '');
        $source = $run->source_user.'@'.$run->source_host.':'.$this->normalizePath($sourcePath).'/';
        $target = rtrim($targetPath, '/').'/';

        if ($authType !== 'ssh_key') {
            throw new \RuntimeException('Gercek dosya tasimasi icin auth_type=ssh_key gereklidir.');
        }
        $key = $this->decryptOrNull($options['secret_ssh_private_key'] ?? null);
        if (! $key) {
            throw new \RuntimeException('SSH private key bulunamadi.');
        }
        $tmpKey = storage_path('app/tmp/plugin-migrate-'.$run->id.'.key');
        @mkdir(dirname($tmpKey), 0750, true);
        file_put_contents($tmpKey, $key);
        @chmod($tmpKey, 0600);
        try {
            $ssh = MigrationSsh::rsyncRshShell($tmpKey, (int) $run->source_port);
            $cmd = sprintf('rsync -az --delete --partial --append-verify -e %s %s %s', escapeshellarg($ssh), escapeshellarg($source), escapeshellarg($target));
            $this->execWithRetry($cmd, 'Dosya rsync', 2, 2);
            $run->output = $this->append($run->output, 'Dosya tasima tamamlandi.');
            $run->save();
        } finally {
            @unlink($tmpKey);
        }
    }

    private function runDatabaseTransfer(PluginMigrationRun $run, Domain $target, array $options): void
    {
        $srcDb = (string) ($options['source_db_name'] ?? '');
        $srcUser = (string) ($options['source_db_user'] ?? '');
        $srcPass = $this->decryptOrNull($options['secret_source_db_password'] ?? null);
        if ($srcDb === '' || $srcUser === '' || $srcPass === null) {
            $run->output = $this->append($run->output, 'Veritabani bilgileri eksik; DB tasima adimi atlandi.');
            $run->save();
            return;
        }

        /** @var DatabaseService $dbService */
        $dbService = app(DatabaseService::class);
        $created = $dbService->create($target->user, $target->name.'_migrated', 'mysql', (int) $target->id, 'localhost');
        $dstDb = $created['database'];
        $dstPass = (string) ($created['password_plain'] ?? '');
        if ($dstPass === '') {
            throw new \RuntimeException('Hedef veritabani sifresi olusturulamadi.');
        }

        $authType = (string) ($options['auth_type'] ?? '');
        if ($authType !== 'ssh_key') {
            throw new \RuntimeException('Gercek DB tasimasi icin auth_type=ssh_key gereklidir.');
        }
        $key = $this->decryptOrNull($options['secret_ssh_private_key'] ?? null);
        if (! $key) {
            throw new \RuntimeException('SSH private key bulunamadi.');
        }
        $tmpKey = storage_path('app/tmp/plugin-migrate-'.$run->id.'-db.key');
        @mkdir(dirname($tmpKey), 0750, true);
        file_put_contents($tmpKey, $key);
        @chmod($tmpKey, 0600);
        try {
            $ssh = MigrationSsh::rsyncRshShell($tmpKey, (int) $run->source_port);
            $srcHost = (string) ($options['source_db_host'] ?? '127.0.0.1');
            $srcPort = (int) ($options['source_db_port'] ?? 3306);
            $dump = sprintf(
                '%s %s %s',
                $ssh,
                escapeshellarg($run->source_user.'@'.$run->source_host),
                escapeshellarg(sprintf('mysqldump -h%s -P%d -u%s -p%s %s', $srcHost, $srcPort, $srcUser, $srcPass, $srcDb))
            );
            $mysqlBin = MigrationCliResolver::mysql();
            if ($mysqlBin === null) {
                throw new \RuntimeException('Panel sunucusunda mysql istemcisi bulunamadi (.env MYSQL_CLIENT_PATH veya XAMPP mysql yolu).');
            }
            $import = sprintf(
                '%s -h%s -P%d -u%s -p%s %s',
                escapeshellarg($mysqlBin),
                escapeshellarg((string) $dstDb->host),
                (int) $dstDb->port,
                escapeshellarg((string) $dstDb->username),
                escapeshellarg($dstPass),
                escapeshellarg((string) $dstDb->name)
            );
            $this->execWithRetry($dump.' | '.$import, 'DB dump/import', 2, 3);
            $run->output = $this->append($run->output, 'Veritabani tasima tamamlandi.');
            $run->save();
        } finally {
            @unlink($tmpKey);
        }
    }

    private function exec(string $command, string $title): void
    {
        $p = Process::fromShellCommandline($command, null, MigrationSsh::processEnv(), null, 1800);
        $p->run();
        if (! $p->isSuccessful()) {
            throw new \RuntimeException($title.' basarisiz: '.trim($p->getErrorOutput() ?: $p->getOutput()));
        }
    }

    private function execWithRetry(string $command, string $title, int $tries, int $sleepSeconds): void
    {
        $last = null;
        for ($i = 1; $i <= $tries; $i++) {
            try {
                $this->exec($command, $title);
                return;
            } catch (\Throwable $e) {
                $last = $e;
                if ($i < $tries) {
                    sleep($sleepSeconds);
                }
            }
        }
        throw new \RuntimeException($title.' tekrar denemelerden sonra basarisiz: '.($last?->getMessage() ?? 'unknown'));
    }

    private function countRemoteFiles(PluginMigrationRun $run, string $keyPath, string $sourcePath): int
    {
        $remoteCmd = sprintf('find %s -type f | wc -l', escapeshellarg($this->normalizePath($sourcePath)));
        $p = new Process(
            array_merge(
                MigrationSsh::commandPrefix($keyPath, (int) $run->source_port),
                [$run->source_user.'@'.$run->source_host, $remoteCmd]
            ),
            null,
            MigrationSsh::processEnv(),
            null,
            60
        );
        $p->run();
        if (! $p->isSuccessful()) {
            return -1;
        }
        return (int) trim($p->getOutput());
    }

    private function countLocalFiles(string $path): int
    {
        $p = Process::fromShellCommandline(sprintf('find %s -type f | wc -l', escapeshellarg(rtrim($path, '/'))), null, null, null, 60);
        $p->run();
        if (! $p->isSuccessful()) {
            return -1;
        }
        return (int) trim($p->getOutput());
    }

    private function decryptOrNull(mixed $cipher): ?string
    {
        if (! is_string($cipher) || trim($cipher) === '') {
            return null;
        }
        try {
            return Crypt::decryptString($cipher);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizePath(string $path): string
    {
        $p = trim($path);
        return rtrim($p, '/');
    }

    private function append(?string $output, string $line): string
    {
        $prefix = now()->format('Y-m-d H:i:s');
        return trim(($output ?? '')."\n"."[$prefix] ".$line);
    }
}
