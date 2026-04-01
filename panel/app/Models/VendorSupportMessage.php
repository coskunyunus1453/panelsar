<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSupportMessage extends Model
{
    protected $fillable = [
        'ticket_id',
        'author_user_id',
        'author_type',
        'message',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(VendorSupportTicket::class, 'ticket_id');
    }
}

