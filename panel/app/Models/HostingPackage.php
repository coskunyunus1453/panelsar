<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HostingPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'disk_space_mb',
        'bandwidth_mb',
        'max_domains',
        'max_subdomains',
        'max_databases',
        'max_email_accounts',
        'max_ftp_accounts',
        'max_cron_jobs',
        'cpu_limit',
        'memory_limit_mb',
        'php_versions',
        'ssl_enabled',
        'backup_enabled',
        'price_monthly',
        'price_yearly',
        'currency',
        'is_active',
        'sort_order',
        'reseller_id',
    ];

    protected function casts(): array
    {
        return [
            'php_versions' => 'array',
            'ssl_enabled' => 'boolean',
            'backup_enabled' => 'boolean',
            'is_active' => 'boolean',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function reseller()
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }
}
