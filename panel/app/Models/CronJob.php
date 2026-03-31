<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CronJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'schedule',
        'command',
        'description',
        'status',
        'last_run_at',
        'next_run_at',
        'engine_job_id',
        'is_system',
        'system_key',
    ];

    protected function casts(): array
    {
        return [
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'is_system' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function runs()
    {
        return $this->hasMany(CronJobRun::class);
    }
}
