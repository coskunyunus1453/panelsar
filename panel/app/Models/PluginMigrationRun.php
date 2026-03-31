<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PluginMigrationRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plugin_module_id',
        'target_domain_id',
        'source_type',
        'source_host',
        'source_port',
        'source_user',
        'status',
        'dry_run',
        'progress',
        'options',
        'output',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'progress' => 'integer',
            'options' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function pluginModule()
    {
        return $this->belongsTo(PluginModule::class, 'plugin_module_id');
    }

    public function targetDomain()
    {
        return $this->belongsTo(Domain::class, 'target_domain_id');
    }
}
