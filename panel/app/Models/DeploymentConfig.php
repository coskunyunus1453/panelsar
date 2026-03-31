<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeploymentConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'repo_url',
        'branch',
        'branch_whitelist',
        'runtime',
        'webhook_token',
        'auto_deploy',
    ];

    protected function casts(): array
    {
        return [
            'auto_deploy' => 'boolean',
            'branch_whitelist' => 'array',
        ];
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }
}
