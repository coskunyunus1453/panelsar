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

    /**
     * Panelde kayıtlı grant_host ile mysql.user.Host bazen farklı olur (localhost vs 127.0.0.1);
     * ALTER USER / DROP USER yanlış host ile 1396 vb. hatalar verir.
     */
    public function resolveActualUserHost(string $dbUser, string $panelHost): string
    {
        $this->assertSafeIdentifier($dbUser);
        $panel = trim($panelHost);
        $this->assertAllowedGrantHost($panel);

        try {
            $pdo = $this->adminPdo();
            $stmt = $pdo->prepare('SELECT Host FROM mysql.user WHERE User = ?');
            $stmt->execute([$dbUser]);
            /** @var list<string> $hosts */
            $hosts = array_values(array_unique(array_map(static fn ($h): string => (string) $h, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
        } catch (PDOException) {
            return $panel;
        }

        if ($hosts === []) {
            throw new \InvalidArgumentException(__('databases.mysql_user_missing', ['user' => $dbUser]));
        }

        if (in_array($panel, $hosts, true)) {
            return $panel;
        }

        if ($panel === 'localhost') {
            foreach (['127.0.0.1', '::1'] as $alt) {
                if (in_array($alt, $hosts, true)) {
                    return $alt;
                }
            }
        }
        if ($panel === '127.0.0.1' && in_array('localhost', $hosts, true)) {
            return 'localhost';
        }

        if (count($hosts) === 1) {
            return $hosts[0];
        }

        throw new \InvalidArgumentException(__('databases.mysql_user_host_ambiguous', [
            'user' => $dbUser,
            'hosts' => implode(', ', $hosts),
        ]));
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
        try {
            $grantHost = $this->resolveActualUserHost($dbUser, trim($grantHost));
        } catch (\InvalidArgumentException) {
            $grantHost = trim($grantHost);
        }

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
        try {
            $grantHost = $this->resolveActualUserHost($dbUser, $grantHost);
        } catch (\InvalidArgumentException) {
            // kullanıcı yoksa veya mysql.user okunamıyorsa paneldeki host ile devam
        }
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

    /**
     * @return string MySQL’de kullanılan gerçek Host değeri (panel satırını güncellemek için).
     */
    public function rotatePassword(string $dbUser, string $grantHost, string $newPassword): string
    {
        $this->assertSafeIdentifier($dbUser);
        $grantHost = $this->resolveActualUserHost($dbUser, $grantHost);

        $pdo = $this->adminPdo();
        $u = $pdo->quote($dbUser);
        $h = $pdo->quote($grantHost);

        $pdo->exec("ALTER USER {$u}@{$h} IDENTIFIED BY ".$pdo->quote($newPassword));
        $pdo->exec('FLUSH PRIVILEGES');

        return $grantHost;
    }

    public function renameDatabase(string $oldDbName, string $newDbName): void
    {
        $this->assertSafeIdentifier($oldDbName);
        $this->assertSafeIdentifier($newDbName);
        if ($oldDbName === $newDbName) {
            return;
        }

        $pdo = $this->adminPdo();
        $oldQ = '`'.$oldDbName.'`';
        $newQ = '`'.$newDbName.'`';
        $oldLike = $oldDbName.'\_%';

        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$newQ} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $stmt = $pdo->prepare(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = ? ORDER BY table_name ASC'
        );
        $stmt->execute([$oldDbName]);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($tables as $table) {
            $tbl = (string) $table;
            if ($tbl === '') {
                continue;
            }
            if (! preg_match('/^[a-zA-Z0-9_.%-]+$/', $tbl)) {
                throw new \InvalidArgumentException('Geçersiz MySQL tablo adı.');
            }
            $tblQ = '`'.$tbl.'`';
            $pdo->exec("RENAME TABLE {$oldQ}.{$tblQ} TO {$newQ}.{$tblQ}");
        }

        $pdo->exec("DROP DATABASE IF EXISTS {$oldQ}");
    }

    public function renameUserAndRegrant(
        string $oldUsername,
        string $newUsername,
        string $grantHost,
        string $dbName,
        string $plainPassword
    ): void {
        $this->assertSafeIdentifier($oldUsername);
        $this->assertSafeIdentifier($newUsername);
        $this->assertAllowedGrantHost($grantHost);
        $this->assertSafeIdentifier($dbName);
        if ($oldUsername === $newUsername) {
            return;
        }

        $pdo = $this->adminPdo();
        $resolvedHost = $this->resolveActualUserHost($oldUsername, trim($grantHost));
        $h = $pdo->quote($resolvedHost);
        $oldU = $pdo->quote($oldUsername);
        $newU = $pdo->quote($newUsername);
        $dbQ = '`'.$dbName.'`';

        $pdo->exec("DROP USER IF EXISTS {$newU}@{$h}");
        $pdo->exec("RENAME USER {$oldU}@{$h} TO {$newU}@{$h}");
        $pdo->exec("ALTER USER {$newU}@{$h} IDENTIFIED BY ".$pdo->quote($plainPassword));
        $pdo->exec("GRANT ALL PRIVILEGES ON {$dbQ}.* TO {$newU}@{$h}");
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

        $oldHost = $this->resolveActualUserHost($dbUser, $oldHost);

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

    /**
     * Sayfa listesi / kota için şema boyutları (MB, ondalıklı).
     *
     * @param  list<string>  $dbNames
     * @return array<string, float> veritabanı adı => MB
     */
    public function sumSizesMbByDatabase(array $dbNames): array
    {
        $dbNames = array_values(array_unique(array_filter(array_map('trim', $dbNames))));
        if ($dbNames === []) {
            return [];
        }
        foreach ($dbNames as $n) {
            $this->assertSafeIdentifier($n);
        }

        $pdo = $this->adminPdo();
        $placeholders = implode(',', array_fill(0, count($dbNames), '?'));
        $sql = <<<SQL
            SELECT s.SCHEMA_NAME AS dbname,
                   COALESCE(ROUND(SUM(t.DATA_LENGTH + t.INDEX_LENGTH) / 1024 / 1024, 3), 0) AS mb
            FROM information_schema.SCHEMATA s
            LEFT JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = s.SCHEMA_NAME
            WHERE s.SCHEMA_NAME IN ({$placeholders})
            GROUP BY s.SCHEMA_NAME
            SQL;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($dbNames);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[(string) $row['dbname']] = (float) $row['mb'];
        }

        return $out;
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
