<?php

namespace App\Services;

use App\Models\Database;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseService
{
    public function __construct(
        private MysqlProvisioner $mysqlProvisioner,
        private PostgresProvisioner $postgresProvisioner,
    ) {}

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
                : (string) config('panelsar.mysql_provision.grant_host', 'localhost');
            if ($this->mysqlProvisioner->enabled()) {
                $this->mysqlProvisioner->provision($dbName, $dbUser, $dbPass, $resolvedGrant);
            }
        }

        if ($type === 'postgresql' && $this->postgresProvisioner->enabled()) {
            $this->postgresProvisioner->provision($dbName, $dbUser, $dbPass);
        }

        $mysqlHost = (string) config('panelsar.mysql_provision.host', '127.0.0.1');
        $mysqlPort = (int) config('panelsar.mysql_provision.port', 3306);
        $pgHost = (string) config('panelsar.postgres_provision.host', '127.0.0.1');
        $pgPort = (int) config('panelsar.postgres_provision.port', 5432);

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

            // Eski kayıtlarda APP_KEY değişimi sonrası encrypted cast çözülmeyebilir
            // (The MAC is invalid). Model->save() dirty-check sırasında decrypt dener.
            // Query update bu kontrolü bypass eder ve yeni şifreyi güvenle yazar.
            Database::query()
                ->whereKey($database->getKey())
                ->update(['password' => $newPass]);

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
            $plain = $database->password;
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
}
