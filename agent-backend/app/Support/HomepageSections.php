<?php

namespace App\Support;

class HomepageSections
{
    public const DEFAULTS = [
        'latest_products' => true,
        'popular_products' => true,
        'hero_banner' => true,
        'featured_categories' => true,
        'featured_products' => true,
        'featured_brands' => true,
        'latest_reviews' => true,
        'buying_guides' => true,
        'popular_posts' => true,
        'newsletter' => true,
    ];

    public static function resolve(?array $saved): array
    {
        return array_replace(self::DEFAULTS, array_intersect_key($saved ?? [], self::DEFAULTS));
    }
}
