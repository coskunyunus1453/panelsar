<?php

namespace App\Services;

use App\Models\HostingPackage;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Hosting paketi limitleri (admin sınırsız; paket yok = sınırsız geriye dönük uyumluluk).
 */
class HostingQuotaService
{
    public function __construct(
        private EngineApiService $engine,
    ) {}

    public function packageFor(User $user): ?HostingPackage
    {
        if ($user->isAdmin()) {
            return null;
        }

        return $user->hostingPackage()->first();
    }

    /** null = sınırsız */
    private function cap(?HostingPackage $pkg, string $field): ?int
    {
        if ($pkg === null) {
            return null;
        }
        $v = (int) ($pkg->{$field} ?? 0);
        if ($v < 0) {
            return null;
        }

        return $v;
    }

    public function ensureCanCreateDomain(User $user): void
    {
        $pkg = $this->packageFor($user);
        $max = $this->cap($pkg, 'max_domains');
        if ($max === null) {
            return;
        }
        if ($user->domains()->count() >= $max) {
            abort(422, __('quota.max_domains', ['max' => $max]));
        }
    }

    public function ensureCanCreateDatabase(User $user): void
    {
        $pkg = $this->packageFor($user);
        $max = $this->cap($pkg, 'max_databases');
        if ($max === null) {
            return;
        }
        if ($user->databases()->count() >= $max) {
            abort(422, __('quota.max_databases', ['max' => $max]));
        }
    }

    public function ensureCanCreateEmailAccount(User $user): void
    {
        $pkg = $this->packageFor($user);
        $max = $this->cap($pkg, 'max_email_accounts');
        if ($max === null) {
            return;
        }
        if ($user->emailAccounts()->count() >= $max) {
            abort(422, __('quota.max_email_accounts', ['max' => $max]));
        }
    }

    public function ensureCanCreateFtpAccount(User $user): void
    {
        $pkg = $this->packageFor($user);
        $max = $this->cap($pkg, 'max_ftp_accounts');
        if ($max === null) {
            return;
        }
        if ($user->ftpAccounts()->count() >= $max) {
            abort(422, __('quota.max_ftp_accounts', ['max' => $max]));
        }
    }

    public function ensureCanCreateCronJob(User $user): void
    {
        $pkg = $this->packageFor($user);
        $max = $this->cap($pkg, 'max_cron_jobs');
        if ($max === null) {
            return;
        }
        if ($user->cronJobs()->count() >= $max) {
            abort(422, __('quota.max_cron_jobs', ['max' => $max]));
        }
    }

    /**
     * @return array{used: int, max: int|null, unlimited: bool}
     */
    public function cronQuotaSummary(User $user): array
    {
        $pkg = $this->packageFor($user);
        $max = $this->cap($pkg, 'max_cron_jobs');
        $used = $user->cronJobs()->count();

        return [
            'used' => $used,
            'max' => $max,
            'unlimited' => $max === null,
        ];
    }

    public function ensureCanQueueBackup(User $user): void
    {
        $pkg = $this->packageFor($user);
        if ($pkg !== null && ! $pkg->backup_enabled) {
            abort(422, __('quota.backups_disabled'));
        }
        $max = (int) config('hostvim.backup.max_backups_per_user', 5);
        if ($max < 0) {
            return;
        }
        if ($user->backups()->count() >= $max) {
            abort(422, __('quota.max_backups', ['max' => $max]));
        }
    }

    public function ensureSslAllowed(User $user): void
    {
        $pkg = $this->packageFor($user);
        if ($pkg !== null && ! $pkg->ssl_enabled) {
            abort(422, __('quota.ssl_disabled'));
        }
    }

    /** Paket disk kotası (bayt); null = sınırsız (admin veya disk_space_mb negatif). */
    public function diskQuotaBytes(?HostingPackage $pkg): ?int
    {
        if ($pkg === null) {
            return null;
        }
        $mb = (int) ($pkg->disk_space_mb ?? -1);
        if ($mb < 0) {
            return null;
        }

        return $mb * 1024 * 1024;
    }

    /**
     * Kullanıcının tüm alan adları için engine üzerinden tahmini toplam disk (bayt).
     */
    public function sumAccountDiskBytes(User $user): int
    {
        if ($user->isAdmin()) {
            return 0;
        }
        $total = 0;
        foreach ($user->domains()->cursor() as $domain) {
            $row = $this->engine->getSiteDiskUsage((string) $domain->name);
            if (empty($row['error'])) {
                $total += (int) ($row['bytes'] ?? 0);
            }
        }

        return $total;
    }

    /**
     * Ek yazma için kota kontrolü (üzerine yazmada şişme riski: içerik boyutu kadar kötümser).
     */
    public function ensureDiskHeadroom(User $user, int $additionalBytes): void
    {
        if ($user->isAdmin()) {
            return;
        }
        $limit = $this->diskQuotaBytes($this->packageFor($user));
        if ($limit === null) {
            return;
        }
        if ($additionalBytes <= 0) {
            return;
        }
        $used = $this->sumAccountDiskBytes($user);
        if ($used + $additionalBytes > $limit) {
            abort(422, __('quota.disk_quota_exceeded', [
                'used_mb' => round($used / 1048576, 1),
                'limit_mb' => round($limit / 1048576, 1),
            ]));
        }
    }

    /**
     * Dosya listesinden tek dosya boyutu (bayt); bulunamazsa 0.
     */
    public function engineFileSizeBytes(string $domain, string $enginePath): int
    {
        $enginePath = str_replace('\\', '/', trim($enginePath));
        $enginePath = ltrim($enginePath, '/');
        if ($enginePath === '' || Str::contains($enginePath, '..')) {
            return 0;
        }
        $base = basename($enginePath);
        $dir = dirname($enginePath);
        $parent = ($dir === '.' || $dir === '') ? '' : $dir;
        $list = $this->engine->listFilesResult($domain, $parent, 5000, 0, 'name', 'asc');
        if ($list['error'] !== null) {
            return 0;
        }
        foreach ($list['entries'] as $e) {
            if (! is_array($e)) {
                continue;
            }
            if (($e['name'] ?? '') !== $base) {
                continue;
            }
            if (! empty($e['is_dir'])) {
                return 0;
            }

            return max(0, (int) ($e['size'] ?? 0));
        }

        return 0;
    }

    /**
     * Zip açılışında sıkıştırma oranı bilinmediği için arşiv boyutunun katı kadar yer ayırır.
     */
    public function estimatedUnzipHeadroomBytes(string $domain, string $engineArchivePath): int
    {
        $sz = $this->engineFileSizeBytes($domain, $engineArchivePath);
        if ($sz <= 0) {
            return 1024;
        }
        $mult = (int) config('hostvim.limits.disk_unzip_expand_multiplier', 4);
        if ($mult < 2) {
            $mult = 4;
        }

        return $sz * $mult;
    }
}
