<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuLocation extends Model
{
    protected $table = 'menu_locations';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $primaryKey = 'key';

    protected $fillable = ['key', 'label', 'theme_slug'];
}