<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Http\Resources\Api\BrandResource;
use App\Http\Resources\Api\CategoryResource;
use App\Http\Resources\Api\PostResource;
use App\Http\Resources\Api\SeoMetaResource;

use App\Support\ImageUrl;
use App\Support\ResolvedSeo;

class AffiliateProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'name' => $this->name,

            'slug' => $this->slug,

            'brand_id' => $this->brand_id,
            'category_id' => $this->category_id,
            'affiliate_network_id' => $this->affiliate_network_id,
            'status' => $this->status,
            'featured_image_path' => $this->featured_image,

            'short_description' => $this->short_description,

            'description' => $this->description,

            'website_url' => $this->website_url,

            'affiliate_url' => $this->affiliate_url,

            'price' => $this->price,

            'currency' => $this->currency,

            'commission_rate' => $this->commission_rate,

            'free_trial' => $this->free_trial,

            'rating' => $this->rating,

            /*
            |--------------------------------------------------------------------------
            | Image
            |--------------------------------------------------------------------------
            */

            'featured_image' => ImageUrl::make(
                $this->featured_image
            ),

            'pros' => $this->pros,

            'cons' => $this->cons,

            'featured' => $this->featured,

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            'brand' => BrandResource::make(
                $this->whenLoaded('brand')
            ),

            'category' => CategoryResource::make(
                $this->whenLoaded('category')
            ),

            'affiliate_network' => AffiliateNetworkResource::make(
                $this->whenLoaded('affiliateNetwork')
            ),

            'seo' => ResolvedSeo::product($this->resource),
        ];
    }
}
