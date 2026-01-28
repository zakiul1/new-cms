<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Widget extends Model
{
    protected $fillable = ['type', 'title', 'settings', 'is_enabled'];

    protected $casts = [
        'settings' => 'array',
        'is_enabled' => 'bool',
    ];
}