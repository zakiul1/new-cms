<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlugHistory extends Model
{
    protected $fillable = ['entity_type', 'entity_id', 'old_slug'];
}