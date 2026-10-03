<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * Transform comment.
     */
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'post_id' => $this->post_id,

            'parent_id' => $this->parent_id,

            'name' => $this->name,

            'website' => $this->website,

            'comment' => $this->comment,

            'status' => $this->status,

            'approved_at' => $this->approved_at,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,

            'post' => $this->whenLoaded('post', function () {

                return [
                    'id' => $this->post->id,
                    'title' => $this->post->title,
                    'slug' => $this->post->slug,
                ];
            }),

            'parent' => $this->whenLoaded('parent', function () {

                return [
                    'id' => $this->parent->id,
                    'name' => $this->parent->name,
                ];
            }),

            'replies' => CommentResource::collection(
                $this->whenLoaded('replies')
            ),
        ];
    }
}