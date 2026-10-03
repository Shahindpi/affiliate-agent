<?php

namespace Database\Seeders;

use App\Models\HeroBanner;
use Illuminate\Database\Seeder;

class HeroBannerSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Find your next useful tool', 'Explore thoughtfully selected software and resources.', '/products', true],
            ['Read practical buying guides', 'Compare options with clear editorial guidance.', '/posts', true],
            ['Explore design ideas', 'Discover tools that support creative work.', '/categories/design-tools', false],
        ] as $index => [$heading, $description, $url, $enabled]) {
            HeroBanner::updateOrCreate(['heading' => $heading], [
                'description' => $description,
                'background_image' => 'uploads/demo/hero-'.($index + 1).'.png',
                'cta_text' => 'Explore now', 'cta_url' => $url,
                'enabled' => $enabled, 'sort_order' => 10 + $index,
            ]);
        }
    }
}
