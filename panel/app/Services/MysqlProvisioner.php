<?php

namespace App\Services;

use PDO;
use PDOException;

class MysqlProvisioner
{
    public function enabled(): bool
    {
        return (bool) config('hostvim.mysql_provision.enabled', false);
    }

    /** Panel doğrulaması: izin listesi veya geçerli IP. */
    public function validateGrantHost(string $host): void
    {
        $this->assertAllowedGrantHost($host);
    }

    public function provision(string $dbName, string $dbUser, string $dbPass, string $grantHost): void
    {
        $this->assertSafeIdentifier($dbName);
        $this->assertSafeIdentifier($dbUser);
        $this->assertAllowedGrantHost($grantHost);

        $pdo = $this->adminPdo();
        $grantHost = trim($grantHost);

        $dbQuoted = '`'.$dbName.'`';
        $u = $pdo->quote($dbUser);
        $h = $pdo->quote($grantHost);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$dbQuoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("DROP USER IF EXISTS {$u}@{$h}");
        $pdo->exec("CREATE USER {$u}@{$h} IDENTIFIED BY ".$pdo->quote($dbPass));
        $pdo->exec("GRANT ALL PRIVILEGES ON {$dbQuoted}.* TO {$u}@{$h}");
        $pdo->exec('FLUSH PRIVILEGES');
    }

    public function drop(string $dbName, string $dbUser, string $grantHost): void
    {
        $this->assertSafeIdentifier($dbName);
        $this->assertSafeIdentifier($dbUser);
        $this->assertAllowedGrantHost($grantHost);

        $pdo = $this->adminPdo();
        $grantHost = trim($grantHost);

        $dbQuoted = '`'.$dbName.'`';
        $u = $pdo->quote($dbUser);
        $h = $pdo->quote($grantHost);

        try {
            $pdo->exec("DROP DATABASE IF EXISTS {$dbQuoted}");
        } catch (PDOException) {
            // ignore
        }
        try {
            $pdo->exec("DROP USER IF EXISTS {$u}@{$h}");
        } catch (PDOException) {
            // ignore
        }
        $pdo->exec('FLUSH PRIVILEGES');
    }

    /**
     * Veritabanını boşaltır (DROP+CREATE); uygulama kullanıcısı kalır, yetkiler yenilenir.
     * İçe aktarımdan önce çağrılır.
     */
    public function recreateEmptyDatabase(string $dbName, string $dbUser, string $grantHost, string $plainPassword): void
    {
        $this->assertSafeIdentifier($dbName);
        $this->assertSafeIdentifier($dbUser);
        $this->assertAllowedGrantHost($grantHost);

        $pdo = $this->adminPdo();
        $grantHost = trim($grantHost);
        $dbQuoted = '`'.$dbName.'`';
        $u = $pdo->quote($dbUser);
        $h = $pdo->quote($grantHost);

        $pdo->exec("DROP DATABASE IF EXISTS {$dbQuoted}");
        $pdo->exec("CREATE DATABASE {$dbQuoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try {
            $pdo->exec("GRANT ALL PRIVILEGES ON {$dbQuoted}.* TO {$u}@{$h}");
        } catch (PDOException) {
            $pdo->exec("CREATE USER {$u}@{$h} IDENTIFIED BY ".$pdo->quote($plainPassword));
            $pdo->exec("GRANT ALL PRIVILEGES ON {$dbQuoted}.* TO {$u}@{$h}");
        }
        $pdo->exec('FLUSH PRIVILEGES');
    }

    public function rotatePassword(string $dbUser, string $grantHost, string $newPassword): void
    {
        $this->assertSafeIdentifier($dbUser);
        $this->assertAllowedGrantHost($grantHost);
        $grantHost = trim($grantHost);

        $pdo = $this->adminPdo();
        $u = $pdo->quote($dbUser);
        $h = $pdo->quote($grantHost);

        $pdo->exec("ALTER USER {$u}@{$h} IDENTIFIED BY ".$pdo->quote($newPassword));
        $pdo->exec('FLUSH PRIVILEGES');
    }

    /**
     * Aynı kullanıcı adıyla MySQL @host değiştirir (GRANT taşınır).
     */
    public function changeGrantHost(string $dbName, string $dbUser, string $oldHost, string $newHost, string $plainPassword): void
    {
        $this->assertSafeIdentifier($dbName);
        $this->assertSafeIdentifier($dbUser);
        $this->assertAllowedGrantHost($oldHost);
        $this->assertAllowedGrantHost($newHost);

        $oldHost = trim($oldHost);
        $newHost = trim($newHost);
        if ($oldHost === $newHost) {
            return;
        }

        $pdo = $this->adminPdo();
        $dbQuoted = '`'.$dbName.'`';
        $u = $pdo->quote($dbUser);
        $hOld = $pdo->quote($oldHost);
        $hNew = $pdo->quote($newHost);

        $pdo->exec("DROP USER IF EXISTS {$u}@{$hNew}");
        $pdo->exec("CREATE USER {$u}@{$hNew} IDENTIFIED BY ".$pdo->quote($plainPassword));
        $pdo->exec("GRANT ALL PRIVILEGES ON {$dbQuoted}.* TO {$u}@{$hNew}");
        $pdo->exec("DROP USER IF EXISTS {$u}@{$hOld}");
        $pdo->exec('FLUSH PRIVILEGES');
    }

    private function adminPdo(): PDO
    {
        $c = config('hostvim.mysql_provision');
        $host = (string) ($c['host'] ?? '127.0.0.1');
        $port = (int) ($c['port'] ?? 3306);
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);

        return new PDO(
            $dsn,
            (string) ($c['username'] ?? 'root'),
            (string) ($c['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]
        );
    }

    private function assertSafeIdentifier(string $name): void
    {
        if ($name === '' || ! preg_match('/^[a-zA-Z0-9_.%-]+$/', $name)) {
            throw new \InvalidArgumentException('Geçersiz MySQL tanımlayıcısı.');
        }
    }

    private function assertAllowedGrantHost(string $host): void
    {
        $host = trim($host);
        if ($host === '') {
            throw new \InvalidArgumentException('GRANT host boş olamaz.');
        }

        $allowed = config('hostvim.mysql_provision.allowed_grant_hosts', []);
        if (is_array($allowed) && in_array($host, $allowed, true)) {
            return;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return;
        }

        throw new \InvalidArgumentException('Bu MySQL erişim host değeri izin listesinde değil ve geçerli bir IP değil.');
    }
}
