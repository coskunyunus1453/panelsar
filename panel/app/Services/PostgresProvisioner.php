<?php

namespace App\Services;

use PDO;
use PDOException;

class PostgresProvisioner
{
    public function enabled(): bool
    {
        return (bool) config('panelsar.postgres_provision.enabled', false);
    }

    public function provision(string $dbName, string $dbUser, string $dbPass): void
    {
        $this->assertSafeIdent($dbName);
        $this->assertSafeIdent($dbUser);

        $pdo = $this->adminPdoToMaintenanceDb();
        $qDb = $this->quoteIdent($dbName);
        $qUser = $this->quoteIdent($dbUser);

        $this->terminateBackends($pdo, $dbName);
        $pdo->exec("DROP DATABASE IF EXISTS {$qDb}");
        $pdo->exec("DROP ROLE IF EXISTS {$qUser}");
        $pdo->exec('CREATE USER '.$qUser.' WITH PASSWORD '.$pdo->quote($dbPass));
        $pdo->exec("CREATE DATABASE {$qDb} OWNER {$qUser}");
        $pdo->exec("GRANT ALL PRIVILEGES ON DATABASE {$qDb} TO {$qUser}");
    }

    public function drop(string $dbName, string $dbUser): void
    {
        $this->assertSafeIdent($dbName);
        $this->assertSafeIdent($dbUser);

        $pdo = $this->adminPdoToMaintenanceDb();
        $qDb = $this->quoteIdent($dbName);
        $qUser = $this->quoteIdent($dbUser);

        try {
            $this->terminateBackends($pdo, $dbName);
            $pdo->exec("DROP DATABASE IF EXISTS {$qDb}");
        } catch (PDOException) {
            // ignore
        }
        try {
            $pdo->exec("DROP ROLE IF EXISTS {$qUser}");
        } catch (PDOException) {
            // ignore
        }
    }

    public function rotatePassword(string $dbUser, string $newPassword): void
    {
        $this->assertSafeIdent($dbUser);
        $pdo = $this->adminPdoToMaintenanceDb();
        $qUser = $this->quoteIdent($dbUser);
        $pdo->exec('ALTER USER '.$qUser.' WITH PASSWORD '.$pdo->quote($newPassword));
    }

    private function terminateBackends(PDO $pdo, string $dbName): void
    {
        $sql = 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '.$pdo->quote($dbName).' AND pid <> pg_backend_pid()';
        try {
            $pdo->query($sql);
        } catch (PDOException) {
            // ignore
        }
    }

    private function adminPdoToMaintenanceDb(): PDO
    {
        $c = config('panelsar.postgres_provision');
        $host = (string) ($c['host'] ?? '127.0.0.1');
        $port = (int) ($c['port'] ?? 5432);
        $maint = (string) ($c['admin_database'] ?? 'postgres');
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $maint);

        return new PDO(
            $dsn,
            (string) ($c['username'] ?? 'postgres'),
            (string) ($c['password'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    private function quoteIdent(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }

    private function assertSafeIdent(string $name): void
    {
        if ($name === '' || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Geçersiz PostgreSQL tanımlayıcısı.');
        }
    }
}
