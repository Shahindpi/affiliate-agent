<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactMessageResource extends JsonResource
{
    /**
     * Transform message.
     */
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'name' => $this->name,

            'email' => $this->email,

            'subject' => $this->subject,

            'message' => $this->message,

            'status' => $this->status,

            'read_at' => $this->read_at,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,

        ];
    }
}