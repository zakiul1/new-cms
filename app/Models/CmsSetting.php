<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsSetting extends Model
{
    protected $fillable = ['group', 'key', 'value'];

    protected $casts = [
        'value' => 'json',
    ];
}