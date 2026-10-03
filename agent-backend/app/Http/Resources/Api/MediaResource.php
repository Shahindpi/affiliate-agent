<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MediaResource extends JsonResource
{
    /**
     * Transform media item into array.
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this['name'],

            'path' => $this['path'],

            'url' => Storage::disk('public')->url($this['path']),

            'size' => $this['size'],

            'last_modified' => $this['last_modified'],
        ];
    }
}