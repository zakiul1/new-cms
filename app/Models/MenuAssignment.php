<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuAssignment extends Model
{
    protected $fillable = ['location_key', 'menu_id'];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(MenuLocation::class, 'location_key', 'key');
    }
}