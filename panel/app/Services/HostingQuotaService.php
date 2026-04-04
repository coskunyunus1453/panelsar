<?php

namespace App\Services;

use App\Models\HostingPackage;
use App\Models\User;

/**
 * Hosting paketi limitleri (admin sınırsız; paket yok = sınırsız geriye dönük uyumluluk).
 */
class HostingQuotaService
{
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
}
