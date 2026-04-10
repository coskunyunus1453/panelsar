<?php

namespace App\Services;

use App\Models\Database;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseService
{
    public function __construct(
        private MysqlProvisioner $mysqlProvisioner,
        private PostgresProvisioner $postgresProvisioner,
    ) {}

    /**
     * Model cast 'encrypted' ile aynı formatta sakla (query()->update cast uygulamaz).
     */
    private function encryptStoredPassword(string $plain): string
    {
        return encrypt($plain);
    }

    /**
     * MySQL/PostgreSQL işlemleri için düz şifre. Eski kayıtlarda düz metin yazılmışsa (cast decrypt patlar) ham değer kullanılır.
     */
    private function plainPasswordForOps(Database $database): string
    {
        try {
            return (string) $database->getAttribute('password');
        } catch (Throwable) {
            $raw = $database->getRawOriginal('password');
            if (! is_string($raw) || $raw === '') {
                throw new \InvalidArgumentException(__('databases.password_unreadable'));
            }
            try {
                return decrypt($raw);
            } catch (Throwable) {
                return $raw;
            }
        }
    }

    /**
     * @return array{database: Database, password_plain: string}
     */
    public function create(User $user, string $name, string $type, ?int $domainId, ?string $grantHost): array
    {
        if ($type === 'mysql') {
            $ghInput = $grantHost !== null && trim($grantHost) !== '' ? trim($grantHost) : null;
            if ($ghInput !== null) {
                $this->mysqlProvisioner->validateGrantHost($ghInput);
            }
        }

        $prefix = 'ps_'.$user->id.'_';
        $slug = Str::slug($name, '_');
        if ($slug === '') {
            $slug = 'db';
        }
        $dbName = substr($prefix.$slug, 0, 63);
        $dbUser = substr($prefix.Str::lower(Str::random(8)), 0, 63);
        $dbPass = Str::random(24);

        $resolvedGrant = null;
        if ($type === 'mysql') {
            $resolvedGrant = $grantHost !== null && trim($grantHost) !== ''
                ? trim($grantHost)
                : (string) config('hostvim.mysql_provision.grant_host', 'localhost');
            if ($this->mysqlProvisioner->enabled()) {
                $this->mysqlProvisioner->provision($dbName, $dbUser, $dbPass, $resolvedGrant);
            }
        }

        if ($type === 'postgresql' && $this->postgresProvisioner->enabled()) {
            $this->postgresProvisioner->provision($dbName, $dbUser, $dbPass);
        }

        $mysqlHost = (string) config('hostvim.mysql_provision.host', '127.0.0.1');
        $mysqlPort = (int) config('hostvim.mysql_provision.port', 3306);
        $pgHost = (string) config('hostvim.postgres_provision.host', '127.0.0.1');
        $pgPort = (int) config('hostvim.postgres_provision.port', 5432);

        return DB::transaction(function () use ($user, $domainId, $type, $dbName, $dbUser, $dbPass, $resolvedGrant, $mysqlHost, $mysqlPort, $pgHost, $pgPort) {
            $database = Database::create([
                'user_id' => $user->id,
                'domain_id' => $domainId,
                'name' => $dbName,
                'type' => $type,
                'username' => $dbUser,
                'password' => $dbPass,
                'host' => $type === 'mysql' ? $mysqlHost : ($type === 'postgresql' ? $pgHost : '127.0.0.1'),
                'port' => $type === 'mysql' ? $mysqlPort : ($type === 'postgresql' ? $pgPort : 5432),
                'grant_host' => $type === 'mysql' ? $resolvedGrant : null,
                'status' => 'active',
            ]);

            return [
                'database' => $database,
                'password_plain' => $dbPass,
            ];
        });
    }

    /**
     * @return array{password_plain: string}
     */
    public function rotatePassword(Database $database): array
    {
        if (! in_array($database->type, ['mysql', 'postgresql'], true)) {
            throw new \InvalidArgumentException(__('databases.rotate_password_unsupported'));
        }

        $newPass = Str::random(24);

        return DB::transaction(function () use ($database, $newPass) {
            if ($database->type === 'mysql') {
                $grant = $database->mysqlGrantHost();
                if ($this->mysqlProvisioner->enabled()) {
                    $this->mysqlProvisioner->rotatePassword($database->username, $grant, $newPass);
                }
            } elseif ($database->type === 'postgresql') {
                if ($this->postgresProvisioner->enabled()) {
                    $this->postgresProvisioner->rotatePassword($database->username, $newPass);
                }
            }

            // Query update cast uygulamaz; 'encrypted' alanı encrypt() ile yazılmalı (aksi halde sonraki okumada decrypt patlar).
            Database::query()
                ->whereKey($database->getKey())
                ->update(['password' => $this->encryptStoredPassword($newPass)]);

            return ['password_plain' => $newPass];
        });
    }

    public function updateGrantHost(Database $database, string $newGrantHost): void
    {
        if ($database->type !== 'mysql') {
            throw new \InvalidArgumentException(__('databases.grant_host_mysql_only'));
        }

        $old = $database->mysqlGrantHost();
        $new = trim($newGrantHost);
        $this->mysqlProvisioner->validateGrantHost($new);
        if ($old === $new) {
            return;
        }

        DB::transaction(function () use ($database, $old, $new) {
            $plain = $this->plainPasswordForOps($database);
            if ($this->mysqlProvisioner->enabled()) {
                $this->mysqlProvisioner->changeGrantHost(
                    $database->name,
                    $database->username,
                    $old,
                    $new,
                    $plain
                );
            }
            $database->grant_host = $new;
            $database->save();
        });
    }

    /**
     * @return array{password_plain?: string}
     */
    public function updateCredentials(
        Database $database,
        ?string $newName,
        ?string $newUsername,
        ?string $newPassword,
        ?string $newGrantHost = null
    ): array {
        $name = trim((string) $newName);
        $username = trim((string) $newUsername);
        $password = trim((string) $newPassword);
        $grantHost = trim((string) $newGrantHost);

        $existingPlain = $this->plainPasswordForOps($database);

        $targetName = $name !== '' ? $name : $database->name;
        $targetUser = $username !== '' ? $username : $database->username;
        $targetPass = $password !== '' ? $password : $existingPlain;
        $targetGrant = $database->type === 'mysql'
            ? ($grantHost !== '' ? $grantHost : $database->mysqlGrantHost())
            : null;

        $changed = $targetName !== $database->name
            || $targetUser !== $database->username
            || $password !== ''
            || ($database->type === 'mysql' && $targetGrant !== $database->mysqlGrantHost());
        if (! $changed) {
            return [];
        }

        DB::transaction(function () use ($database, $targetName, $targetUser, $targetPass, $targetGrant): void {
            if ($database->type === 'mysql') {
                $oldName = $database->name;
                $oldUser = $database->username;
                $oldGrant = $database->mysqlGrantHost();
                $newGrant = (string) $targetGrant;

                if ($this->mysqlProvisioner->enabled()) {
                    if ($oldName !== $targetName) {
                        $this->mysqlProvisioner->renameDatabase($oldName, $targetName);
                    }
                    if ($oldUser !== $targetUser) {
                        $this->mysqlProvisioner->renameUserAndRegrant(
                            $oldUser,
                            $targetUser,
                            $oldGrant,
                            $targetName,
                            $targetPass
                        );
                    } elseif ($oldGrant !== $newGrant) {
                        $this->mysqlProvisioner->changeGrantHost(
                            $targetName,
                            $targetUser,
                            $oldGrant,
                            $newGrant,
                            $targetPass
                        );
                    }
                    if ($targetPass !== $existingPlain && $oldUser === $targetUser) {
                        $this->mysqlProvisioner->rotatePassword($targetUser, $newGrant, $targetPass);
                    }
                }

                $database->grant_host = $newGrant;
            } elseif ($database->type === 'postgresql') {
                $oldName = $database->name;
                $oldUser = $database->username;

                if ($this->postgresProvisioner->enabled()) {
                    if ($oldName !== $targetName) {
                        $this->postgresProvisioner->renameDatabase($oldName, $targetName);
                    }
                    if ($oldUser !== $targetUser) {
                        $this->postgresProvisioner->renameRole($oldUser, $targetUser);
                    }
                    if ($targetPass !== $existingPlain || $oldUser !== $targetUser) {
                        $this->postgresProvisioner->rotatePassword($targetUser, $targetPass);
                    }
                }
            }

            Database::query()
                ->whereKey($database->getKey())
                ->update([
                    'name' => $targetName,
                    'username' => $targetUser,
                    'password' => $this->encryptStoredPassword($targetPass),
                    'grant_host' => $database->type === 'mysql' ? $database->grant_host : null,
                ]);
        });

        return $password !== '' ? ['password_plain' => $targetPass] : [];
    }

    public function delete(Database $database): void
    {
        $name = $database->name;
        $username = $database->username;
        $type = $database->type;
        $grantHost = $database->mysqlGrantHost();

        if ($type === 'mysql' && $this->mysqlProvisioner->enabled()) {
            try {
                $this->mysqlProvisioner->drop($name, $username, $grantHost);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($type === 'postgresql' && $this->postgresProvisioner->enabled()) {
            try {
                $this->postgresProvisioner->drop($name, $username);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $database->delete();
    }

    /**
     * @param  callable(string): void  $writeChunk
     */
    public function streamMysqlDump(Database $database, callable $writeChunk): void
    {
        if ($database->type !== 'mysql') {
            throw new \InvalidArgumentException(__('databases.export_not_mysql'));
        }
        if (! $this->mysqlProvisioner->enabled()) {
            throw new \InvalidArgumentException(__('databases.provision_disabled_export'));
        }

        $bin = (string) config('hostvim.database_tools.mysqldump_path', 'mysqldump');
        $args = [
            $bin,
            '-h', $database->host,
            '-P', (string) $database->port,
            '-u', $database->username,
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--set-charset',
            '--default-character-set=utf8mb4',
            $database->name,
        ];

        $plain = $this->plainPasswordForOps($database);
        $process = new Process($args, null, ['MYSQL_PWD' => $plain], null, 3600.0);
        $process->run(function (string $type, string $buffer) use ($writeChunk): void {
            if ($type === Process::OUT) {
                $writeChunk($buffer);
            }
        });

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'mysqldump failed');
        }
    }

    /**
     * @param  callable(string): void  $writeChunk
     */
    public function streamPostgresDump(Database $database, callable $writeChunk): void
    {
        if ($database->type !== 'postgresql') {
            throw new \InvalidArgumentException(__('databases.export_not_postgresql'));
        }
        if (! $this->postgresProvisioner->enabled()) {
            throw new \InvalidArgumentException(__('databases.provision_disabled_export'));
        }

        $bin = (string) config('hostvim.database_tools.pg_dump_path', 'pg_dump');
        $args = [
            $bin,
            '-h', $database->host,
            '-p', (string) $database->port,
            '-U', $database->username,
            '--no-owner',
            '--no-acl',
            $database->name,
        ];

        $plain = $this->plainPasswordForOps($database);
        $process = new Process($args, null, ['PGPASSWORD' => $plain], null, 3600.0);
        $process->run(function (string $type, string $buffer) use ($writeChunk): void {
            if ($type === Process::OUT) {
                $writeChunk($buffer);
            }
        });

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'pg_dump failed');
        }
    }

    public function importMysqlFromSqlFile(Database $database, string $absolutePath): void
    {
        if ($database->type !== 'mysql') {
            throw new \InvalidArgumentException(__('databases.import_not_mysql'));
        }
        if (! $this->mysqlProvisioner->enabled()) {
            throw new \InvalidArgumentException(__('databases.provision_disabled_import'));
        }
        if (! is_readable($absolutePath)) {
            throw new \InvalidArgumentException(__('databases.import_file_unreadable'));
        }

        $plain = $this->plainPasswordForOps($database);
        $this->mysqlProvisioner->recreateEmptyDatabase(
            $database->name,
            $database->username,
            $database->mysqlGrantHost(),
            $plain,
        );

        $bin = (string) config('hostvim.database_tools.mysql_path', 'mysql');
        $sql = file_get_contents($absolutePath);
        if ($sql === false) {
            throw new \RuntimeException(__('databases.import_file_unreadable'));
        }

        $process = new Process(
            [
                $bin,
                '-h', $database->host,
                '-P', (string) $database->port,
                '-u', $database->username,
                $database->name,
            ],
            null,
            ['MYSQL_PWD' => $plain],
            $sql,
            3600.0,
        );
        $process->mustRun();
    }

    public function importPostgresFromSqlFile(Database $database, string $absolutePath): void
    {
        if ($database->type !== 'postgresql') {
            throw new \InvalidArgumentException(__('databases.import_not_postgresql'));
        }
        if (! $this->postgresProvisioner->enabled()) {
            throw new \InvalidArgumentException(__('databases.provision_disabled_import'));
        }
        if (! is_readable($absolutePath)) {
            throw new \InvalidArgumentException(__('databases.import_file_unreadable'));
        }

        $this->postgresProvisioner->recreateEmptyDatabase($database->name, $database->username);

        $bin = (string) config('hostvim.database_tools.psql_path', 'psql');
        $plain = $this->plainPasswordForOps($database);
        $process = new Process(
            [
                $bin,
                '-h', $database->host,
                '-p', (string) $database->port,
                '-U', $database->username,
                '-d', $database->name,
                '-v', 'ON_ERROR_STOP=1',
                '-f', $absolutePath,
            ],
            null,
            ['PGPASSWORD' => $plain],
            null,
            3600.0,
        );
        $process->mustRun();
    }
}
