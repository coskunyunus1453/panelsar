<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Database extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain_id',
        'name',
        'type',
        'username',
        'password',
        'host',
        'port',
        'grant_host',
        'size_mb',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
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

    /** MySQL kullanıcısı için @host (boşsa panel varsayılanı). */
    public function mysqlGrantHost(): string
    {
        $g = trim((string) $this->grant_host);

        return $g !== '' ? $g : (string) config('panelsar.mysql_provision.grant_host', 'localhost');
    }
}
