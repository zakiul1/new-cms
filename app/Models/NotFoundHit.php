<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotFoundHit extends Model
{
    protected $fillable = [
        'path',
        'hits',
        'first_hit_at',
        'last_hit_at',
        'last_referrer',
        'last_user_agent',
        'last_ip',
    ];

    protected $casts = [
        'hits' => 'int',
        'first_hit_at' => 'datetime',
        'last_hit_at' => 'datetime',
    ];
}