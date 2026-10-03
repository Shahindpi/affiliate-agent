<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\ImageUrl;

class CategoryResource extends JsonResource
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

            'parent_id' => $this->parent_id,

            'description' => $this->description,
            'image' => ImageUrl::make($this->image),
            'image_path' => $this->image,
            'status' => (bool) $this->status,
            'sort_order' => (int) $this->sort_order,

            'posts_count' => isset($this->posts_count)
                ? (int) $this->posts_count
                : null,
        ];
    }
}
