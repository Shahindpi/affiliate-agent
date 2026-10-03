<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPageSize;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\ContactMessage;

use App\Http\Resources\Api\ContactMessageResource;

use App\Support\ApiResponse;

class ContactMessageController extends Controller
{
    /**
     * Contact inbox.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContactMessage::query();

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($builder) use ($search) {

                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('status')) {

            $query->where('status', $request->status);
        }

        $messages = $query
            ->latest()
            ->paginate(
                AdminPageSize::resolve($request, $query, 15)
            );

        return response()->json([
            'success' => true,
            'message' => 'Contact messages retrieved successfully.',
            'data' => ContactMessageResource::collection(
                $messages->items()
            ),
            'links' => [
                'first' => $messages->url(1),
                'last' => $messages->url($messages->lastPage()),
                'prev' => $messages->previousPageUrl(),
                'next' => $messages->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $messages->currentPage(),
                'from' => $messages->firstItem(),
                'last_page' => $messages->lastPage(),
                'path' => $messages->path(),
                'per_page' => $messages->perPage(),
                'to' => $messages->lastItem(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Show contact message.
     */
    public function show(
        ContactMessage $message
    ): JsonResponse {

        /*
        |--------------------------------------------------------------------------
        | Automatically Mark as Read
        |--------------------------------------------------------------------------
        */

        if ($message->status === 'new') {

            $message->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }

        return ApiResponse::success(
            ContactMessageResource::make($message),
            'Contact message retrieved successfully.'
        );
    }

    /**
     * Mark message as read.
     */
    public function markRead(
        ContactMessage $message
    ): JsonResponse {

        if ($message->status === 'new') {

            $message->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }

        return ApiResponse::success(
            ContactMessageResource::make($message),
            'Message marked as read.'
        );
    }

    /**
     * Archive message.
     */
    public function archive(
        ContactMessage $message
    ): JsonResponse {

        $message->update([
            'status' => 'archived',
        ]);

        return ApiResponse::success(
            ContactMessageResource::make($message),
            'Message archived successfully.'
        );
    }

    /**
     * Restore archived message.
     */
    public function restore(
        ContactMessage $message
    ): JsonResponse {

        $message->update([
            'status' => 'new',
            'read_at' => null,
        ]);

        return ApiResponse::success(
            ContactMessageResource::make($message),
            'Message restored successfully.'
        );
    }

    /**
     * Delete contact message.
     */
    public function destroy(
        ContactMessage $message
    ): JsonResponse {

        $message->delete();

        return ApiResponse::success(
            [],
            'Message deleted successfully.'
        );
    }


}