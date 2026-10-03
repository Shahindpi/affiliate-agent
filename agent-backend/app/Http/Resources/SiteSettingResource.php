<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\ImageUrl;
use App\Support\HomepageSections;

class SiteSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'homepage_sections' => HomepageSections::resolve($this->homepage_sections),

            'site_name' => $this->site_name,
            'site_tagline' => $this->site_tagline,
            'site_url' => $this->site_url,

            'logo' => ImageUrl::make($this->logo),
            'favicon' => ImageUrl::make($this->favicon),

            'default_meta_title' => $this->default_meta_title,
            'default_meta_description' => $this->default_meta_description,
            'default_meta_keywords' => $this->default_meta_keywords,

            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'contact_address' => $this->contact_address,

            'social' => [
                'facebook' => $this->facebook,
                'twitter' => $this->twitter,
                'linkedin' => $this->linkedin,
                'github' => $this->github,
                'youtube' => $this->youtube,
                'instagram' => $this->instagram,
            ],

            'analytics' => [
                'google_analytics_id' => $this->google_analytics_id,
                'google_search_console_id' => $this->google_search_console_id,
            ],

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
