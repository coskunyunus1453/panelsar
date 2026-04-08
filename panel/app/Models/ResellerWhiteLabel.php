<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerWhiteLabel extends Model
{
    protected $fillable = [
        'user_id',
        'slug',
        'hostname',
        'primary_color',
        'secondary_color',
        'logo_customer_basename',
        'logo_admin_basename',
        'login_title',
        'login_subtitle',
        'mail_footer_plain',
        'onboarding_html',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
