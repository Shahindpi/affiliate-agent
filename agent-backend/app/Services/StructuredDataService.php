<?php

namespace App\Services;

use App\Models\Post;
use App\Models\AffiliateProduct;
use App\Support\ImageUrl;

class StructuredDataService
{
    /**
     * Generate JSON-LD for a blog post.
     */
    public static function article(Post $post): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',

            'headline' => $post->title,

            'description' => $post->excerpt,

            'datePublished' => optional($post->published_at)->toIso8601String(),

            'dateModified' => optional($post->updated_at)->toIso8601String(),

            'author' => [
                '@type' => 'Person',
                'name' => optional($post->user)->name,
            ],

            'publisher' => [
                '@type' => 'Organization',
                'name' => config('app.name'),
            ],

            'mainEntityOfPage' => config('app.frontend_url')
                . '/posts/' . $post->slug,

            'image' => ImageUrl::make($post->featured_image),
        ];
    }

    /**
     * Generate JSON-LD for an affiliate product.
     */
    public static function product(AffiliateProduct $product): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',

            'name' => $product->name,

            'description' => $product->short_description,

            'image' => ImageUrl::make($product->featured_image),

            'brand' => [
                '@type' => 'Brand',
                'name' => optional($product->brand)->name,
            ],

            'offers' => [
                '@type' => 'Offer',

                'price' => $product->price,

                'priceCurrency' => $product->currency,

                'availability' => 'https://schema.org/InStock',

                'url' => config('app.frontend_url')
                    . '/products/' . $product->slug,
            ],
        ];
    }
}