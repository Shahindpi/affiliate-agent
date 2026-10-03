<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoMeta extends Model
{
    use HasFactory;

    protected $fillable = [
        'seoable_type',
        'seoable_id',
        'meta_title',
        'meta_description',
        'canonical_url',
        'og_title',
        'og_description',
        'og_image',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'robots',
        'schema_data',
    ];

    protected function casts(): array
    {
        return [
            'schema_data' => 'array',
        ];
    }

    /**
     * Parent model (Post or Affiliate Product).
     */
    public function seoable()
    {
        return $this->morphTo();
    }
}