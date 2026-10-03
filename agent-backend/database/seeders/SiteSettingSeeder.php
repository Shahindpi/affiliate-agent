<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use App\Support\HomepageSections;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::firstOrCreate(['id' => 1], [
            'homepage_sections' => HomepageSections::DEFAULTS,
            'site_name' => 'Dewdora', 'site_tagline' => 'Find useful products and practical guidance',
            'logo' => 'uploads/demo/dewdora-logo.png',
            'default_meta_title' => 'Dewdora',
            'default_meta_description' => 'Discover useful products, reviews and buying guides.',
        ]);
    }
}
