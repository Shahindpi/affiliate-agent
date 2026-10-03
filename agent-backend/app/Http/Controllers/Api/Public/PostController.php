<?php

namespace App\Http\Controllers\Api\Public;
use Illuminate\Support\Facades\Cache;
use App\Services\CacheService;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Resources\Api\PostResource;
use App\Support\ApiResponse;
use App\Http\Resources\Api\Collections\PaginatedApiCollection;


class PostController extends Controller
{
    /**
     * List published posts.
     */
    public function index(Request $request)
    {
        $perPage = min(
            max($request->integer('per_page', 10), 1),
            50
        );

        /*
        |--------------------------------------------------------------------------
        | Cache Key
        |--------------------------------------------------------------------------
        */

        $cacheKey = 'public_posts_' . Cache::get('public_cache_version', 0) . '_' . md5(
            json_encode($request->query())
        );

        /*
        |--------------------------------------------------------------------------
        | Get Posts (Cached)
        |--------------------------------------------------------------------------
        */

        $posts = Cache::remember(
            $cacheKey,
            now()->addMinutes(10),
            function () use ($request, $perPage) {

                return Post::query()

                    ->select([
                        'id',
                        'user_id',
                        'category_id',
                        'title',
                        'slug',
                        'excerpt',
                        'featured_image',
                        'post_type',
                        'status',
                        'published_at',
                        'views',
                        'reading_time',
                        'allow_comments',
                    ])

                    ->published()

                    ->with([
                        'category:id,name,slug',
                        'user:id,name',

                        'tags:id,name,slug',

                        'seoMeta:id,seoable_id,seoable_type,meta_title,meta_description,canonical_url',
                    ])

                    /*
                    |--------------------------------------------------------------------------
                    | Category Filter
                    |--------------------------------------------------------------------------
                    */

                    ->when(
                        $request->filled('category'),
                        function ($query) use ($request) {
                            $query->whereHas(
                                'category',
                                function ($categoryQuery) use ($request) {
                                    $categoryQuery->where(
                                        'slug',
                                        $request->string('category')
                                    );
                                }
                            );
                        }
                    )

                    /*
                    |--------------------------------------------------------------------------
                    | Tag Filter
                    |--------------------------------------------------------------------------
                    */

                    ->when(
                        $request->filled('tag'),
                        function ($query) use ($request) {
                            $query->whereHas(
                                'tags',
                                function ($tagQuery) use ($request) {
                                    $tagQuery->where(
                                        'slug',
                                        $request->string('tag')
                                    );
                                }
                            );
                        }
                    )

                    /*
                    |--------------------------------------------------------------------------
                    | Search Filter
                    |--------------------------------------------------------------------------
                    */

                    ->when(
                        $request->filled('search'),
                        function ($query) use ($request) {

                            $search = trim($request->string('search'));

                            $query->where(function ($q) use ($search) {
                                $q->where('title', 'like', "%{$search}%")
                                ->orWhere('excerpt', 'like', "%{$search}%");
                            });
                        }
                    )

                    /*
                    |--------------------------------------------------------------------------
                    | Sorting
                    |--------------------------------------------------------------------------
                    */

                    ->when(
                        $request->filled('sort'),
                        function ($query) use ($request) {

                            match ($request->string('sort')->value()) {

                                'oldest' => $query->orderBy('published_at'),

                                'popular' => $query->orderByDesc('views'),

                                default => $query->orderByDesc('published_at'),
                            };
                        },
                        fn ($query) => $query->orderByDesc('published_at')
                    )

                    ->paginate($perPage);
            }
        );

        return ApiResponse::paginated(
            PostResource::collection($posts),
            'Posts retrieved successfully.'
        );
    }

    /**
     * Popular published posts.
     */
    public function popular(): JsonResponse
    {
        $posts = Cache::remember(
            CacheService::popularPostsKey(),
            now()->addHours(6),
            function () {

                return Post::query()
                    ->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->with([
                        'category',
                        'tags',
                        'seoMeta.seoable',
                    ])
                    ->orderByDesc('views')
                    ->orderByDesc('published_at')
                    ->limit(6)
                    ->get();
            }
        );

        return ApiResponse::success(
            PostResource::collection($posts),
            'Popular posts retrieved successfully.'
        );
    }

    /**
     * Show a single published post.
     */
    public function show(string $slug)
    {
        /*
        |--------------------------------------------------------------------------
        | Find the Post (Cached)
        |--------------------------------------------------------------------------
        */

        $post = Cache::remember(
            "public_post_{$slug}",
            now()->addMinutes(15),
            function () use ($slug) {

                return Post::query()

                    ->select([
                        'id',
                        'user_id',
                        'category_id',
                        'title',
                        'slug',
                        'excerpt',
                        'content',
                        'featured_image',
                        'post_type',
                        'status',
                        'published_at',
                        'views',
                        'reading_time',
                        'allow_comments',
                    ])

                    ->published()

                    ->with([

                        'category:id,name,slug',
                        'user:id,name',

                        'tags:id,name,slug',

                        'seoMeta',

                        'affiliateProducts' => function ($query) {

                            $query
                                ->where('status', true)

                                ->with([
                                    'brand:id,name,slug,logo',
                                    'category:id,name,slug',
                                    'seoMeta',
                                ])

                                ->orderByDesc('post_product.is_primary')
                                ->orderBy('post_product.sort_order');
                        },

                    ])

                    ->where('slug', $slug)

                    ->firstOrFail();
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Increment View Count
        |--------------------------------------------------------------------------
        */

        Post::whereKey($post->id)->increment('views');

        /*
        |--------------------------------------------------------------------------
        | Related Posts
        |--------------------------------------------------------------------------
        */

        $tagIds = $post->tags
            ->pluck('id')
            ->all();

        $relatedPosts = Post::query()

            ->select([
                'id',
                'category_id',
                'title',
                'slug',
                'excerpt',
                'featured_image',
                'reading_time',
                'published_at',
                'views',
            ])

            ->published()

            ->with([
                'category:id,name,slug',
                'tags:id,name,slug',
            ])

            ->where('id', '!=', $post->id)

            ->where(function ($query) use ($post, $tagIds) {

                $query->where('category_id', $post->category_id);

                if (! empty($tagIds)) {

                    $query->orWhereHas(
                        'tags',
                        function ($tagQuery) use ($tagIds) {
                            $tagQuery->whereIn('tags.id', $tagIds);
                        }
                    );
                }
            })

            ->latest('published_at')

            ->limit(6)

            ->get();

        /*
        |--------------------------------------------------------------------------
        | Return Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            [
                'post' => new PostResource($post),

                'related_posts' => PostResource::collection($relatedPosts),
            ],
            'Post retrieved successfully.'
        );
    }
}
