<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPageSize;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\Api\PostResource;
use App\Models\Post;
use App\Services\CacheService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PostController extends Controller
{
    /**
     * Display a listing of posts.
     */
    public function index(Request $request)
    {
        $query = Post::query()
            ->with([
                'category',
                'user',
            ])
            ->withCount([
                'tags',
                'affiliateProducts',
            ])
            ->latest();

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = $request->string('search');

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Post Type Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('post_type')) {
            $query->where(
                'post_type',
                $request->string('post_type')
            );
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $perPage = AdminPageSize::resolve($request, $query, 15);

        $posts = $query->paginate($perPage);

        return ApiResponse::paginated(
            PostResource::collection($posts),
            'Posts retrieved successfully.'
        );
    }

    /**
     * Store a newly created post.
     */
    public function store(StorePostRequest $request)
    {
        $validated = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Generate Slug
        |--------------------------------------------------------------------------
        */

        $validated['slug'] = $validated['slug']
            ?? Str::slug($validated['title']);

        /*
        |--------------------------------------------------------------------------
        | Author
        |--------------------------------------------------------------------------
        */

        $validated['user_id'] = Auth::id();

        /*
        |--------------------------------------------------------------------------
        | Defaults
        |--------------------------------------------------------------------------
        */

        $validated['post_type'] =
            $validated['post_type'] ?? 'article';

        $validated['status'] =
            $validated['status'] ?? 'draft';

        if ($validated['status'] === 'published' && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $validated['reading_time'] =
            $validated['reading_time'] ?? 1;

        $validated['allow_comments'] =
            $validated['allow_comments'] ?? false;

        /*
        |--------------------------------------------------------------------------
        | Create Post
        |--------------------------------------------------------------------------
        */

        $post = Post::create($validated);
        CacheService::clearPublicCaches();
        CacheService::clearDashboardCaches();

        /*
        |--------------------------------------------------------------------------
        | Load Relationships
        |--------------------------------------------------------------------------
        */

        $post->load([
            'category',
            'user',
        ]);

        return ApiResponse::success(
            new PostResource($post),
            'Post created successfully.',
            201
        );
    }

    /**
     * Synchronize tags for a post.
     */
    public function syncTags(
        Request $request,
        Post $post
    ): JsonResponse {

        $validated = $request->validate([
            'tag_ids' => [
                'present',
                'array',
            ],

            'tag_ids.*' => [
                'integer',
                'exists:tags,id',
            ],
        ]);

        $post->tags()->sync(
            $validated['tag_ids']
        );
        CacheService::clearPostCaches($post->slug);

        $post->load('tags');

        return response()->json([
            'success' => true,
            'message' => 'Post tags updated successfully.',
            'data' => [
                'post_id' => $post->id,
                'tags' => $post->tags,
            ],
        ]);
    }

    /**
     * Synchronize affiliate products for a post.
     */
    public function syncAffiliateProducts(
        Request $request,
        Post $post
    ): JsonResponse {

        $validated = $request->validate([
            'products' => [
                'present',
                'array',
            ],

            'products.*.affiliate_product_id' => [
                'required',
                'integer',
                'exists:affiliate_products,id',
            ],

            'products.*.sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'products.*.is_primary' => [
                'nullable',
                'boolean',
            ],
        ]);

        $syncData = [];

        foreach ($validated['products'] as $product) {

            $syncData[
                $product['affiliate_product_id']
            ] = [
                'sort_order' => $product['sort_order'] ?? 0,

                'is_primary' => $product['is_primary'] ?? false,
            ];
        }

        $post->affiliateProducts()->sync(
            $syncData
        );
        CacheService::clearPostCaches($post->slug);

        $post->load([
            'affiliateProducts',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Affiliate products updated successfully.',

            'data' => [
                'post_id' => $post->id,

                'products' => $post->affiliateProducts,
            ],
        ]);
    }

    /**
     * Display the specified post.
     */
    public function show(Post $post)
    {
        $post->load([
            'category',
            'user',
            'tags',
            'affiliateProducts',
            'seoMeta',
        ]);

        return ApiResponse::success(
            new PostResource($post),
            'Post retrieved successfully.'
        );
    }

    /**
     * Update the specified post.
     */
    public function update(
        UpdatePostRequest $request,
        Post $post
    ) {

        $validated = $request->validated();
        $oldSlug = $post->slug;

        /*
        |--------------------------------------------------------------------------
        | Slug
        |--------------------------------------------------------------------------
        */

        if (
            isset($validated['title']) &&
            ! isset($validated['slug'])
        ) {
            $validated['slug'] =
                Str::slug($validated['title']);
        }

        /*
        |--------------------------------------------------------------------------
        | Update
        |--------------------------------------------------------------------------
        */

        if (($validated['status'] ?? null) === 'published' && ! $post->published_at && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $post->update($validated);
        CacheService::clearPostCaches($oldSlug);
        CacheService::clearPost($post->slug);

        /*
        |--------------------------------------------------------------------------
        | Reload
        |--------------------------------------------------------------------------
        */

        $post->refresh();

        $post->load([
            'category',
            'user',
        ]);

        return ApiResponse::success(
            new PostResource($post),
            'Post updated successfully.'
        );
    }

    public function destroy(Post $post): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Clear Cache Before Delete
        |--------------------------------------------------------------------------
        */

        CacheService::clearPost($post->slug);

        $post->delete();
        CacheService::clearPublicCaches();
        CacheService::clearDashboardCaches();

        return response()->json([
            'success' => true,
            'message' => 'Post deleted successfully.',
        ]);
    }
}
