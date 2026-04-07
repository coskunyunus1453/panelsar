<?php

namespace App\Support;

use Symfony\Component\Process\Process;

/**
 * Migration eklentisi: panel sunucusunda mysql/mariadb istemcisinin yolunu bulur.
 * XAMPP/macOS'ta mysql genelde PATH'te değildir; tam yol ile çalışır.
 */
final class MigrationCliResolver
{
    /**
     * @return list<string>
     */
    public static function defaultMysqlPaths(): array
    {
        return [
            '/Applications/XAMPP/xamppfiles/bin/mysql',
            '/opt/homebrew/opt/mysql-client/bin/mysql',
            '/usr/local/opt/mysql-client/bin/mysql',
            '/usr/local/mysql/bin/mysql',
            '/usr/bin/mysql',
        ];
    }

    public static function mysql(): ?string
    {
        $cfg = trim((string) config('hostvim.database_tools.mysql_path', 'mysql'));
        if ($cfg !== '' && $cfg !== 'mysql' && $cfg !== 'mariadb' && is_executable($cfg)) {
            return $cfg;
        }

        foreach (self::defaultMysqlPaths() as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        foreach (['mysql', 'mariadb'] as $name) {
            $p = new Process(['sh', '-lc', 'command -v '.escapeshellarg($name)]);
            $p->run();
            if (! $p->isSuccessful()) {
                continue;
            }
            $line = trim($p->getOutput());
            if ($line !== '' && is_executable($line)) {
                return $line;
            }
        }

        return null;
    }
}
