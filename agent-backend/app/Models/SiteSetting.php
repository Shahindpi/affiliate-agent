<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'homepage_sections',

        'site_name',
        'site_tagline',
        'site_url',

        'logo',
        'favicon',

        'default_meta_title',
        'default_meta_description',
        'default_meta_keywords',

        'contact_email',
        'contact_phone',
        'contact_address',

        'facebook',
        'twitter',
        'linkedin',
        'github',
        'youtube',
        'instagram',

        'google_analytics_id',
        'google_search_console_id',
    ];

    protected function casts(): array
    {
        return ['homepage_sections' => 'array'];
    }
}
