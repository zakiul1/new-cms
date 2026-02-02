<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $table = 'slug_redirects';

    protected $fillable = [
        'from_path',
        'to_path',
        'status_code',
    ];
}