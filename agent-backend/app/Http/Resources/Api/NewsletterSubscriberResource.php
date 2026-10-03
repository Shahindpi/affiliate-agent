<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsletterSubscriberResource extends JsonResource
{
    /**
     * Transform subscriber.
     */
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'email' => $this->email,

            'name' => $this->name,

            'status' => $this->status,

            'subscribed_at' => $this->subscribed_at,

            'unsubscribed_at' => $this->unsubscribed_at,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,

        ];
    }
}