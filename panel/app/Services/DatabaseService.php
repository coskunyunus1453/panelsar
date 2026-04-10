<?php

namespace App\Services;

use App\Models\Database;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
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
     * mysql / mysqldump / psql: localhost soket kullanır; kullanıcı çoğu zaman @127.0.0.1 veya TCP ile eşleşir → 1045.
     */
    private function cliConnectHost(Database $database): string
    {
        $h = trim((string) $database->host);
        if ($h === '' || strcasecmp($h, 'localhost') === 0 || $h === '::1') {
            return '127.0.0.1';
        }

        return $h;
    }

    public function hydrateDatabaseSizesOnPaginator(LengthAwarePaginator $paginator): void
    {
        $collection = $paginator->getCollection();
        $mysql = $collection->filter(fn (Database $d) => $d->type === 'mysql');
        if ($mysql->isNotEmpty() && $this->mysqlProvisioner->enabled()) {
            $names = $mysql->pluck('name')->unique()->values()->all();
            $map = $this->mysqlProvisioner->sumSizesMbByDatabase($names);
            foreach ($mysql as $db) {
                $mb = (int) round((float) ($map[$db->name] ?? 0.0));
                if ($mb !== (int) $db->getRawOriginal('size_mb')) {
                    Database::query()->whereKey($db->getKey())->update(['size_mb' => $mb]);
                }
                $db->setAttribute('size_mb', $mb);
            }
        }

        $pg = $collection->filter(fn (Database $d) => $d->type === 'postgresql');
        if ($pg->isNotEmpty() && $this->postgresProvisioner->enabled()) {
            foreach ($pg as $db) {
                $mb = (int) round($this->postgresProvisioner->databaseSizeMb($db->name));
                if ($mb !== (int) $db->getRawOriginal('size_mb')) {
                    Database::query()->whereKey($db->getKey())->update(['size_mb' => $mb]);
                }
                $db->setAttribute('size_mb', $mb);
            }
        }
    }

    public function refreshSingleDatabaseSize(Database $database): void
    {
        if ($database->type === 'mysql' && $this->mysqlProvisioner->enabled()) {
            $map = $this->mysqlProvisioner->sumSizesMbByDatabase([(string) $database->name]);
            $mb = (int) round((float) ($map[$database->name] ?? 0.0));
            Database::query()->whereKey($database->getKey())->update(['size_mb' => $mb]);

            return;
        }
        if ($database->type === 'postgresql' && $this->postgresProvisioner->enabled()) {
            $mb = (int) round($this->postgresProvisioner->databaseSizeMb($database->name));
            Database::query()->whereKey($database->getKey())->update(['size_mb' => $mb]);
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
            $row = ['password' => $this->encryptStoredPassword($newPass)];

            if ($database->type === 'mysql') {
                $grant = $database->mysqlGrantHost();
                if ($this->mysqlProvisioner->enabled()) {
                    $actualHost = $this->mysqlProvisioner->rotatePassword($database->username, $grant, $newPass);
                    $row['grant_host'] = $actualHost;
                }
            } elseif ($database->type === 'postgresql') {
                if ($this->postgresProvisioner->enabled()) {
                    $this->postgresProvisioner->rotatePassword($database->username, $newPass);
                }
            }

            Database::query()
                ->whereKey($database->getKey())
                ->update($row);

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
     * @return array{password_plain?: string, app_config_reminder?: true}
     */
    public function updateCredentials(
        Database $database,
        ?string $newPassword,
        ?string $newGrantHost = null
    ): array {
        $password = trim((string) $newPassword);
        $grantHost = trim((string) $newGrantHost);

        $existingPlain = $this->plainPasswordForOps($database);

        $targetName = $database->name;
        $targetUser = $database->username;
        $targetPass = $password !== '' ? $password : $existingPlain;
        $targetGrant = $database->type === 'mysql'
            ? ($grantHost !== '' ? $grantHost : $database->mysqlGrantHost())
            : null;

        $changed = $password !== ''
            || ($database->type === 'mysql' && $targetGrant !== $database->mysqlGrantHost());
        if (! $changed) {
            return [];
        }

        $remindAppConfig = $password !== '';

        DB::transaction(function () use ($database, $targetName, $targetUser, $targetPass, $targetGrant, $existingPlain): void {
            $mysqlStoredGrant = $database->type === 'mysql' ? (string) $targetGrant : null;

            if ($database->type === 'mysql') {
                $oldGrant = $database->mysqlGrantHost();
                $newGrant = (string) $targetGrant;
                $grantChanged = $oldGrant !== $newGrant;

                if ($this->mysqlProvisioner->enabled()) {
                    if ($grantChanged) {
                        $this->mysqlProvisioner->changeGrantHost(
                            $targetName,
                            $targetUser,
                            $oldGrant,
                            $newGrant,
                            $targetPass
                        );
                        $mysqlStoredGrant = $newGrant;
                    }
                    if ($targetPass !== $existingPlain && ! $grantChanged) {
                        $mysqlStoredGrant = $this->mysqlProvisioner->rotatePassword($targetUser, $newGrant, $targetPass);
                    }
                }
            } elseif ($database->type === 'postgresql') {
                if ($this->postgresProvisioner->enabled() && $targetPass !== $existingPlain) {
                    $this->postgresProvisioner->rotatePassword($targetUser, $targetPass);
                }
            }

            Database::query()
                ->whereKey($database->getKey())
                ->update([
                    'name' => $targetName,
                    'username' => $targetUser,
                    'password' => $this->encryptStoredPassword($targetPass),
                    'grant_host' => $database->type === 'mysql' ? $mysqlStoredGrant : null,
                ]);
        });

        $out = [];
        if ($password !== '') {
            $out['password_plain'] = $targetPass;
        }
        if ($remindAppConfig) {
            $out['app_config_reminder'] = true;
        }

        return $out;
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
            '-h', $this->cliConnectHost($database),
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
            '-h', $this->cliConnectHost($database),
            '-p', (string) $database->port,
            '-U', $database->username,
            '--no-owner',
            '--no-acl',
            '--encoding=UTF8',
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

        $input = fopen($absolutePath, 'rb');
        if ($input === false) {
            throw new \RuntimeException(__('databases.import_file_unreadable'));
        }

        try {
            // --one-database: dump içindeki başka veritabanlarına yönelen USE/DDL satırlarını yok sayar (hedef DB’ye yanlış yazmayı önler).
            $process = new Process(
                [
                    $bin,
                    '-h', $this->cliConnectHost($database),
                    '-P', (string) $database->port,
                    '-u', $database->username,
                    '--default-character-set=utf8mb4',
                    '--one-database',
                    $database->name,
                ],
                null,
                ['MYSQL_PWD' => $plain],
                $input,
                3600.0,
            );
            $process->mustRun();
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
        }

        $this->refreshSingleDatabaseSize($database->fresh());
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
                '-h', $this->cliConnectHost($database),
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

        $this->refreshSingleDatabaseSize($database->fresh());
    }
}
