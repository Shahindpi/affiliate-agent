<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\ImageUrl;
use App\Support\ResolvedSeo;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'title' => $this->title,

            'slug' => $this->slug,

            'category_id' => $this->category_id,

            'excerpt' => $this->excerpt,

            'content' => $this->content,

            'featured_image' => ImageUrl::make($this->featured_image),
            'featured_image_path' => $this->featured_image,

            'post_type' => $this->post_type,

            'status' => $this->status,

            'published_at' => $this->published_at,

            'views' => $this->views,

            'reading_time' => $this->reading_time,

            'allow_comments' => $this->allow_comments,
            'created_at' => $this->created_at?->toISOString(),

            'author' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            'category' => CategoryResource::make(
                $this->whenLoaded('category')
            ),

            'tags' => TagResource::collection(
                $this->whenLoaded('tags')
            ),

            'affiliate_products' =>
                AffiliateProductResource::collection(
                    $this->whenLoaded('affiliateProducts')
                ),

            'seo' => ResolvedSeo::post($this->resource),
        ];
    }
}
