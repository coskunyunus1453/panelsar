<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronJobRun extends Model
{
    protected $fillable = [
        'cron_job_id',
        'user_id',
        'status',
        'exit_code',
        'output',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function cronJob()
    {
        return $this->belongsTo(CronJob::class);
    }
}
