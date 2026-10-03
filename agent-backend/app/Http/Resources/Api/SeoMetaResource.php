<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Post;
use App\Models\AffiliateProduct;

use App\Services\StructuredDataService;
use App\Support\ImageUrl;

class SeoMetaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        /*
        |--------------------------------------------------------------------------
        | Resolve Parent Model
        |--------------------------------------------------------------------------
        */

        $seoable = $this->resource->relationLoaded('seoable')
            ? $this->seoable
            : null;

        /*
        |--------------------------------------------------------------------------
        | Fallback Values
        |--------------------------------------------------------------------------
        */

        $title = $this->meta_title
            ?? $seoable?->title
            ?? $seoable?->name
            ?? config('app.name');

        $description = $this->meta_description
            ?? $seoable?->excerpt
            ?? $seoable?->short_description
            ?? $seoable?->description
            ?? '';

        $canonicalUrl = $this->canonical_url;

        $image = ImageUrl::make(
            $this->og_image
                ?? $seoable?->featured_image
                ?? $seoable?->logo
        );

        /*
        |--------------------------------------------------------------------------
        | Open Graph Type
        |--------------------------------------------------------------------------
        */

        $type = $seoable instanceof Post
            ? 'article'
            : 'website';

        /*
        |--------------------------------------------------------------------------
        | Structured Data
        |--------------------------------------------------------------------------
        */

        $structuredData = null;

        if ($seoable instanceof Post) {
            $structuredData = StructuredDataService::article($seoable);
        }

        if ($seoable instanceof AffiliateProduct) {
            $structuredData = StructuredDataService::product($seoable);
        }

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return [

            'meta_title' => $title,

            'meta_description' => $description,

            'canonical_url' => $canonicalUrl,

            'robots' => $this->robots,

            /*
            |--------------------------------------------------------------------------
            | Open Graph
            |--------------------------------------------------------------------------
            */

            'open_graph' => [
                'title' => $this->og_title ?? $title,
                'description' => $this->og_description ?? $description,
                'image' => $image,
                'url' => $canonicalUrl,
                'type' => $type,
            ],

            /*
            |--------------------------------------------------------------------------
            | Twitter
            |--------------------------------------------------------------------------
            */

            'twitter' => [
                'card' => 'summary_large_image',
                'title' => $this->twitter_title ?? $title,
                'description' => $this->twitter_description ?? $description,
                'image' => ImageUrl::make(
                    $this->twitter_image
                        ?? $this->og_image
                        ?? $seoable?->featured_image
                        ?? $seoable?->logo
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | JSON-LD Structured Data
            |--------------------------------------------------------------------------
            */

            'structured_data' => $structuredData,
        ];
    }
}