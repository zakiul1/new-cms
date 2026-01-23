<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Taxonomy extends Model
{
    protected $fillable = ['key', 'label', 'hierarchical'];

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }
}