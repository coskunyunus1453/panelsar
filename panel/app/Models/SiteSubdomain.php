<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSubdomain extends Model
{
    protected $fillable = [
        'domain_id',
        'hostname',
        'path_segment',
        'document_root',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }
}
