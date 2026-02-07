<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidgetPlacement extends Model
{
    protected $fillable = [
        'widget_area_key',
        'widget_id',
        'sort_order',
        'overrides',
        'visibility',
    ];

    protected $casts = [
        'overrides' => 'array',
        'visibility' => 'array',
    ];

    public function widget(): BelongsTo
    {
        return $this->belongsTo(Widget::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(WidgetArea::class, 'widget_area_key', 'key');
    }
}