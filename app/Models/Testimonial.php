<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = [
        'author_name',
        'author_initials',
        'rating',
        'content',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'rating'       => 'integer',
    ];

    /** Scope : témoignages visibles sur la vitrine. */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
