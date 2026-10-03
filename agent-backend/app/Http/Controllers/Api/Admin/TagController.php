<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPageSize;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Services\CacheService;
use App\Support\ApiResponse;
use App\Http\Resources\Api\TagResource;

use App\Http\Requests\UpdateTagRequest;
use App\Http\Requests\StoreTagRequest;



class TagController extends Controller
{
    /**
     * List tags.
     */
    public function index(Request $request)
    {
        $query = Tag::query()
            ->withCount('posts')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->string('search');

            $query->where('name', 'like', "%{$search}%");
        }

        $tags = $query->paginate(AdminPageSize::resolve($request, $query, 20));

        return ApiResponse::paginated(
            TagResource::collection($tags),
            'Tags retrieved successfully.'
        );
    }


    /**
     * Create tag.
     */
    public function store(StoreTagRequest $request)
    {
        $validated = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Generate Slug
        |--------------------------------------------------------------------------
        */

        $validated['slug'] =
            $validated['slug']
            ?? Str::slug($validated['name']);

        /*
        |--------------------------------------------------------------------------
        | Create Tag
        |--------------------------------------------------------------------------
        */

        $tag = Tag::create($validated);
        CacheService::clearPublicCaches();


        return ApiResponse::success(
            new TagResource($tag),
            'Tag created successfully.',
            201
        );
    }

    /**
     * Show tag.
     */
    public function show(Tag $tag)
    {
        $tag->loadCount('posts');

        return ApiResponse::success(
            new TagResource($tag),
            'Tag retrieved successfully.'
        );
    }


    /**
     * Update tag.
     */
    public function update(
        UpdateTagRequest $request,
        Tag $tag
    ) {

        /*
        |--------------------------------------------------------------------------
        | Store Previous Slug
        |--------------------------------------------------------------------------
        */

        $oldSlug = $tag->slug;

        /*
        |--------------------------------------------------------------------------
        | Validate Request
        |--------------------------------------------------------------------------
        */

        $validated = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Generate Slug
        |--------------------------------------------------------------------------
        */

        if (
            isset($validated['name']) &&
            !isset($validated['slug'])
        ) {
            $validated['slug'] =
                Str::slug($validated['name']);
        }

        /*
        |--------------------------------------------------------------------------
        | Update Tag
        |--------------------------------------------------------------------------
        */

        $tag->update($validated);

        /*
        |--------------------------------------------------------------------------
        | Refresh Model
        |--------------------------------------------------------------------------
        */

        $tag->refresh();

        /*
        |--------------------------------------------------------------------------
        | Clear Cache
        |--------------------------------------------------------------------------
        */

        CacheService::clearTag($oldSlug);
        CacheService::clearTag($tag->slug);
        CacheService::clearPublicCaches();

        return ApiResponse::success(
            new TagResource(
                $tag->fresh()->loadCount('posts')
            ),
            'Tag updated successfully.'
        );
    }


    /**
     * Delete tag.
     */
    public function destroy(Tag $tag): JsonResponse
    {

        CacheService::clearTag($tag->slug);
        $tag->delete();
        CacheService::clearPublicCaches();


        return response()->json([
            'success' => true,
            'message' => 'Tag deleted successfully.',
        ]);
    }
}
