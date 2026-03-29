<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DnsRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'type',
        'name',
        'value',
        'ttl',
        'priority',
    ];

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }
}
