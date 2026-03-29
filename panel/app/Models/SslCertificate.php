<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SslCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'provider',
        'type',
        'status',
        'issued_at',
        'expires_at',
        'auto_renew',
        'certificate_path',
        'private_key_path',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    public function isExpiringSoon(): bool
    {
        return $this->expires_at?->diffInDays(now()) < 30;
    }
}
