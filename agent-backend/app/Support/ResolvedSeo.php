<?php

namespace App\Support;

use App\Models\AffiliateProduct;
use App\Models\Post;
use Illuminate\Support\Str;

class ResolvedSeo
{
    public static function post(Post $post): array
    {
        $seo = $post->relationLoaded('seoMeta') ? $post->seoMeta : null;
        $description = $post->excerpt ?: Str::limit(trim(strip_tags((string) $post->content)), 160, '');
        $frontend = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $image = ImageUrl::make($seo?->og_image ?: $post->featured_image);

        return [
            'meta_title' => $seo?->meta_title ?: $post->title,
            'meta_description' => $seo?->meta_description ?: $description,
            'canonical_url' => $seo?->canonical_url ?: "{$frontend}/posts/{$post->slug}",
            'open_graph' => [
                'title' => $seo?->og_title ?: ($seo?->meta_title ?: $post->title),
                'description' => $seo?->og_description ?: ($seo?->meta_description ?: $description),
                'image' => $image,
                'type' => 'article',
            ],
            'twitter' => [
                'title' => $seo?->twitter_title ?: ($seo?->og_title ?: $post->title),
                'description' => $seo?->twitter_description ?: ($seo?->og_description ?: $description),
                'image' => ImageUrl::make($seo?->twitter_image) ?: $image,
            ],
            'robots' => $seo?->robots ?: 'index,follow',
            'automatic' => [
                'title' => $post->title,
                'description' => $description,
                'image' => ImageUrl::make($post->featured_image),
                'canonical_url' => "{$frontend}/posts/{$post->slug}",
            ],
            'overrides' => [
                'meta_title' => $seo?->meta_title,
                'meta_description' => $seo?->meta_description,
                'canonical_url' => $seo?->canonical_url,
                'og_image' => $seo?->og_image,
            ],
            'has_overrides' => (bool) $seo,
        ];
    }

    public static function product(AffiliateProduct $product): array
    {
        $seo = $product->relationLoaded('seoMeta') ? $product->seoMeta : null;
        $description = $product->short_description ?: Str::limit(trim(strip_tags((string) $product->description)), 160, '');
        $frontend = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $image = ImageUrl::make($seo?->og_image ?: $product->featured_image);

        return [
            'meta_title' => $seo?->meta_title ?: $product->name,
            'meta_description' => $seo?->meta_description ?: $description,
            'canonical_url' => $seo?->canonical_url ?: "{$frontend}/products/{$product->slug}",
            'open_graph' => [
                'title' => $seo?->og_title ?: ($seo?->meta_title ?: $product->name),
                'description' => $seo?->og_description ?: ($seo?->meta_description ?: $description),
                'image' => $image,
                'type' => 'website',
            ],
            'robots' => $seo?->robots ?: 'index,follow',
        ];
    }
}
