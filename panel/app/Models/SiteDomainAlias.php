<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteDomainAlias extends Model
{
    protected $fillable = [
        'domain_id',
        'hostname',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }
}
