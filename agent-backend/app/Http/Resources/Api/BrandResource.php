<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\ImageUrl;

class BrandResource extends JsonResource
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

            'website' => $this->website,
            'logo' => ImageUrl::make($this->logo),
            'logo_path' => $this->logo,
            'status' => (bool) $this->status,
            'description' => $this->description,

            'affiliate_products_count' =>
                $this->whenCounted('affiliateProducts'),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
