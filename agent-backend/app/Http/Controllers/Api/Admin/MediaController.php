<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteMediaRequest;
use App\Http\Requests\ReplaceMediaRequest;
use App\Http\Requests\UploadMediaRequest;
use App\Http\Resources\Api\MediaResource;
use App\Models\AffiliateProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Post;
use App\Models\SeoMeta;
// Models
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /**
     * Media library listing.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim($request->input('search', ''));

        $files = collect(Storage::disk('public')->files('uploads/images'))
            ->map(function ($path) {

                return [
                    'name' => basename($path),

                    'path' => $path,

                    'size' => Storage::disk('public')->size($path),

                    'last_modified' => Storage::disk('public')->lastModified($path),
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($search !== '') {
            $files = $files->filter(function ($file) use ($search) {
                return str_contains(
                    strtolower($file['name']),
                    strtolower($search)
                );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Latest First
        |--------------------------------------------------------------------------
        */

        $files = $files
            ->sortByDesc('last_modified')
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $perPage = $request->input('per_page') === 'all' ? max(1, $files->count()) : min(max($request->integer('per_page', 20), 1), 100);

        $page = max((int) $request->input('page', 1), 1);

        $paginated = $files->slice(
            ($page - 1) * $perPage,
            $perPage
        )->values();

        return ApiResponse::success([
            'media' => MediaResource::collection($paginated),

            'pagination' => [
                'current_page' => $page,

                'per_page' => $perPage,

                'total' => $files->count(),

                'last_page' => (int) ceil(
                    $files->count() / $perPage
                ),
            ],
        ], 'Media library retrieved successfully.');
    }

    /**
     * Upload a new image.
     */
    public function upload(UploadMediaRequest $request): JsonResponse
    {
        $file = $request->file('image');

        /*
        |--------------------------------------------------------------------------
        | Generate Unique Filename
        |--------------------------------------------------------------------------
        */

        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();

        /*
        |--------------------------------------------------------------------------
        | Store Image
        |--------------------------------------------------------------------------
        */

        $path = $file->storeAs(
            'uploads/images',
            $filename,
            'public'
        );

        /*
        |--------------------------------------------------------------------------
        | Image Information
        |--------------------------------------------------------------------------
        */

        $absolutePath = Storage::disk('public')->path($path);

        [$width, $height] = getimagesize($absolutePath);

        $size = Storage::disk('public')->size($path);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success([
            'name' => $filename,

            'original_name' => $file->getClientOriginalName(),

            'path' => $path,

            'url' => Storage::disk('public')->url($path),

            'mime_type' => $file->getMimeType(),

            'extension' => $file->getClientOriginalExtension(),

            'size' => $size,

            'size_human' => $this->formatBytes($size),

            'width' => $width,

            'height' => $height,

            'uploaded_at' => now()->toIso8601String(),
        ], 'Image uploaded successfully.', 201);
    }

    /**
     * Replace an existing image.
     */
    public function replace(
        ReplaceMediaRequest $request
    ): JsonResponse {

        /*
        |--------------------------------------------------------------------------
        | Delete Old Image
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled('old_path') &&
            Storage::disk('public')->exists($request->old_path)
        ) {
            Storage::disk('public')->delete(
                $request->old_path
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Upload New Image
        |--------------------------------------------------------------------------
        */

        $file = $request->file('image');

        $filename =
            Str::uuid().'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs(
            'uploads/images',
            $filename,
            'public'
        );

        $absolutePath =
            Storage::disk('public')->path($path);

        [$width, $height] = getimagesize($absolutePath);

        $size = Storage::disk('public')->size($path);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success([

            'name' => $filename,

            'original_name' => $file->getClientOriginalName(),

            'path' => $path,

            'url' => Storage::disk('public')->url($path),

            'mime_type' => $file->getMimeType(),

            'extension' => $file->getClientOriginalExtension(),

            'size' => $size,

            'size_human' => $this->formatBytes($size),

            'width' => $width,

            'height' => $height,

            'uploaded_at' => now()->toIso8601String(),

        ], 'Image replaced successfully.');
    }

    /**
     * Delete an image safely.
     */
    public function destroy(
        DeleteMediaRequest $request
    ): JsonResponse {

        $path = $request->string('path');

        /*
        |--------------------------------------------------------------------------
        | Check Resource Usage
        |--------------------------------------------------------------------------
        */

        $usedBy = collect();

        Post::where('featured_image', $path)
            ->get()
            ->each(fn ($post) => $usedBy->push(
                "Post: {$post->title}"
            ));

        Brand::where('logo', $path)
            ->get()
            ->each(fn ($brand) => $usedBy->push(
                "Brand: {$brand->name}"
            ));

        Category::where('image', $path)
            ->get()
            ->each(fn ($category) => $usedBy->push(
                "Category: {$category->name}"
            ));

        AffiliateProduct::where('featured_image', $path)
            ->get()
            ->each(fn ($product) => $usedBy->push(
                "Affiliate Product: {$product->name}"
            ));

        SeoMeta::where('og_image', $path)
            ->orWhere('twitter_image', $path)
            ->get()
            ->each(fn () => $usedBy->push(
                'SEO Meta'
            ));

        /*
        |--------------------------------------------------------------------------
        | Prevent Deletion
        |--------------------------------------------------------------------------
        */

        if ($usedBy->isNotEmpty()) {

            return ApiResponse::error(
                "Image is currently used by {$usedBy->count()} resources.",
                [
                    'used_by' => $usedBy->values(),
                ],
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Delete File
        |--------------------------------------------------------------------------
        */

        if (! Storage::disk('public')->exists($path)) {

            return ApiResponse::error(
                'Image not found.',
                null, 404
            );
        }

        Storage::disk('public')->delete($path);

        return ApiResponse::success(
            [],
            'Image deleted successfully.'
        );
    }

    /**
     * Convert bytes into human-readable size.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' Bytes';
    }
}
