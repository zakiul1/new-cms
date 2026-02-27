<?php

namespace Plugins\SiatexTags\Models;

use Illuminate\Database\Eloquent\Model;

class SiatexTag extends Model
{
    protected $table = 'siatex_tags';

    protected $fillable = [
        'slug',
        'title',
        'content_json',
        'meta_json',
        'media_category_term_id',
    ];

    protected $casts = [
        'content_json' => 'array',
        'meta_json' => 'array',
        'media_category_term_id' => 'integer',
    ];
}