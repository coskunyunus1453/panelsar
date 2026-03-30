<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Domain extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id',
        'name',
        'document_root',
        'php_version',
        'ssl_enabled',
        'ssl_expiry',
        'status',
        'is_primary',
        'server_type',
    ];

    protected function casts(): array
    {
        return [
            'ssl_enabled' => 'boolean',
            'ssl_expiry' => 'datetime',
            'is_primary' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'ssl_enabled', 'php_version'])
            ->logOnlyDirty();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sslCertificate()
    {
        return $this->hasOne(SslCertificate::class);
    }

    public function databases()
    {
        return $this->hasMany(Database::class);
    }

    public function emailAccounts()
    {
        return $this->hasMany(EmailAccount::class);
    }

    public function dnsRecords()
    {
        return $this->hasMany(DnsRecord::class);
    }

    public function backups()
    {
        return $this->hasMany(Backup::class);
    }

    public function ftpAccounts()
    {
        return $this->hasMany(FtpAccount::class);
    }

    /** Alt alan adları (örn. blog.example.com) — fiziksel dizin site kökü altında. */
    public function siteSubdomains()
    {
        return $this->hasMany(SiteSubdomain::class, 'domain_id');
    }

    /** Bu siteye eklenen ek alan adları (aynı belge kökü). */
    public function siteDomainAliases()
    {
        return $this->hasMany(SiteDomainAlias::class, 'domain_id');
    }
}
