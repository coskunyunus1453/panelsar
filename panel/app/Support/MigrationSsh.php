<?php

namespace App\Support;

/**
 * Panel PHP süreci (www-data) altında SSH: HOME=/var/www olunca OpenSSH /var/www/.ssh
 * oluşturmaya çalışır ve Permission denied verir. Yazılabilir HOME + known_hosts kullanır.
 */
final class MigrationSsh
{
    public static function prepareStateDirectory(): void
    {
        $home = self::homeDirectory();
        if (! is_dir($home)) {
            @mkdir($home, 0750, true);
        }
        $dotSsh = $home.DIRECTORY_SEPARATOR.'.ssh';
        if (! is_dir($dotSsh)) {
            @mkdir($dotSsh, 0700, true);
        }
        $kh = self::knownHostsPath();
        if (! is_file($kh)) {
            @touch($kh);
            @chmod($kh, 0644);
        }
    }

    public static function homeDirectory(): string
    {
        return storage_path('app/ssh-home');
    }

    public static function knownHostsPath(): string
    {
        self::prepareStateDirectory();

        return self::homeDirectory().DIRECTORY_SEPARATOR.'known_hosts';
    }

    /**
     * Symfony Process için tam ortam (HOME ile birlikte).
     *
     * @return array<string, string>
     */
    public static function processEnv(): array
    {
        self::prepareStateDirectory();

        $env = [];
        foreach ($_SERVER as $k => $v) {
            if (is_string($v)) {
                $env[$k] = $v;
            }
        }
        foreach ($_ENV as $k => $v) {
            if (is_string($v)) {
                $env[$k] = $v;
            }
        }
        $env['HOME'] = self::homeDirectory();
        if (! isset($env['PATH']) || $env['PATH'] === '') {
            $env['PATH'] = '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
        }

        return $env;
    }

    /**
     * Process(['ssh', ...]) biçimi: user@host ve uzak komuttan önce.
     *
     * @return list<string>
     */
    public static function commandPrefix(string $identityFile, int $port): array
    {
        self::prepareStateDirectory();

        return [
            'ssh',
            '-i', $identityFile,
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile='.self::knownHostsPath(),
            '-o', 'GlobalKnownHostsFile=/dev/null',
            '-o', 'ConnectTimeout=15',
            '-p', (string) $port,
        ];
    }

    /**
     * rsync -e için tek shell argümanı (Process ortamında HOME da set edilir).
     */
    public static function rsyncRshShell(string $identityFile, int $port): string
    {
        self::prepareStateDirectory();

        return sprintf(
            'ssh -i %s -o BatchMode=yes -o StrictHostKeyChecking=no -o UserKnownHostsFile=%s -o GlobalKnownHostsFile=/dev/null -o ConnectTimeout=15 -p %d',
            escapeshellarg($identityFile),
            escapeshellarg(self::knownHostsPath()),
            $port
        );
    }
}
