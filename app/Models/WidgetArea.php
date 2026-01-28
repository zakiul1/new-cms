<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WidgetArea extends Model
{
    protected $table = 'widget_areas';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $primaryKey = 'key';

    protected $fillable = ['key', 'label', 'theme_slug'];
}