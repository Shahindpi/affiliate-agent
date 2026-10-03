<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AffiliateNetworkResource extends JsonResource
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
            'description' => $this->description,
            'status' => (bool) $this->status,

            'affiliate_products_count' =>
                $this->whenCounted('affiliateProducts'),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
