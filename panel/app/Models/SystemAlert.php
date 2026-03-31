<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAlert extends Model
{
    protected $fillable = [
        'level',
        'title',
        'message',
        'path',
        'dedupe_key',
    ];
}

