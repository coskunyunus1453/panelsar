<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailForwarder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain_id',
        'source',
        'destination',
        'keep_copy',
    ];

    protected function casts(): array
    {
        return [
            'keep_copy' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }
}
