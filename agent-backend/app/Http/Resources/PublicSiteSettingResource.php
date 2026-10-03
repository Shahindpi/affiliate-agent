<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\ImageUrl;
use App\Support\HomepageSections;

class PublicSiteSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'homepage_sections' => HomepageSections::resolve($this->homepage_sections),

            'site_name' => $this->site_name,
            'google_analytics_id' => $this->google_analytics_id,

            'site_tagline' => $this->site_tagline,

            'site_url' => $this->site_url,

            'logo' => ImageUrl::make($this->logo),

            'favicon' => ImageUrl::make($this->favicon),

            'seo' => [
                'meta_title' => $this->default_meta_title,
                'meta_description' => $this->default_meta_description,
                'meta_keywords' => $this->default_meta_keywords,
            ],

            'contact' => [
                'email' => $this->contact_email,
                'phone' => $this->contact_phone,
                'address' => $this->contact_address,
            ],

            'social' => [
                'facebook' => $this->facebook,
                'twitter' => $this->twitter,
                'linkedin' => $this->linkedin,
                'github' => $this->github,
                'youtube' => $this->youtube,
                'instagram' => $this->instagram,
            ],

        ];
    }
}
