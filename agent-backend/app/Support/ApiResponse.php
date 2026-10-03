<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ApiResponse
{
    public static function nestedPage(AnonymousResourceCollection $items, LengthAwarePaginator $page): array
    {
        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }
    /**
     * Successful JSON response.
     */
    public static function success(
        mixed $data = null,
        string $message = 'Success.',
        int $status = 200
    ): JsonResponse {

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Error JSON response.
     */
    public static function error(
        string $message = 'Something went wrong.',
        mixed $errors = null,
        int $status = 400
    ): JsonResponse {

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Paginated Resource Response.
     */
    public static function paginated(
        AnonymousResourceCollection $resource,
        string $message = 'Success.'
    ): AnonymousResourceCollection {

        return $resource->additional([
            'success' => true,
            'message' => $message,
        ]);
    }
}
