<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;

use Illuminate\Http\JsonResponse;

use App\Http\Requests\StoreContactMessageRequest;

use App\Http\Resources\Api\ContactMessageResource;

use App\Models\ContactMessage;

use App\Support\ApiResponse;

class ContactController extends Controller
{
    /**
     * Store a contact message.
     */
    public function store(
        StoreContactMessageRequest $request
    ): JsonResponse {

        $message = ContactMessage::create([

            'name' => trim($request->name),

            'email' => strtolower(trim($request->email)),

            'subject' => trim($request->subject),

            'message' => trim($request->message),

            'status' => 'new',

            'ip_address' => $request->ip(),

            'user_agent' => $request->userAgent(),

        ]);

        return ApiResponse::success(

            ContactMessageResource::make($message),

            'Your message has been sent successfully.',

            201

        );
    }
}