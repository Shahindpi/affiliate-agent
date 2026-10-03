<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PostResource;
use App\Http\Resources\Api\TagResource;
use App\Models\Tag;
use Illuminate\Http\Request;
use App\Support\ApiResponse;
use App\Http\Resources\Api\Collections\PaginatedApiCollection;
use Illuminate\Support\Facades\Cache;

class TagController extends Controller
{
    /**
     * List public tags.
     */
    public function index(Request $request)
    {
        $perPage = min(
            max($request->integer('per_page', 20), 1),
            50
        );

        /*
        |--------------------------------------------------------------------------
        | Cache Key
        |--------------------------------------------------------------------------
        */

        $cacheKey = 'public_tags_' . Cache::get('public_cache_version', 0) . '_' . md5(
            json_encode($request->query())
        );

        /*
        |--------------------------------------------------------------------------
        | Get Tags (Cached)
        |--------------------------------------------------------------------------
        */

        $tags = Cache::remember(
            $cacheKey,
            now()->addMinutes(30),
            function () use ($request, $perPage) {

                return Tag::query()
                    ->withCount([
                        'posts' => function ($query) {
                            $query
                                ->where('status', 'published')
                                ->whereNotNull('published_at')
                                ->where('published_at', '<=', now());
                        },
                    ])

                    // Search
                    ->when(
                        $request->filled('search'),
                        function ($query) use ($request) {
                            $query->where(
                                'name',
                                'like',
                                '%' . $request->string('search') . '%'
                            );
                        }
                    )

                    ->orderBy('name')

                    ->paginate($perPage);
            }
        );

        return TagResource::collection($tags)
            ->additional([
                'success' => true,
            ]);
    }

    /**
     * Show tag with published posts.
     */
    public function show(
        Request $request,
        string $slug
    ) {
        /*
        |--------------------------------------------------------------------------
        | Pagination safety
        |--------------------------------------------------------------------------
        */

        $perPage = min(
            max(
                $request->integer('per_page', 10),
                1
            ),
            50
        );


        /*
        |--------------------------------------------------------------------------
        | Find tag
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | Find Tag (Cached)
        |--------------------------------------------------------------------------
        */

        $tag = Cache::remember(
            "public_tag_{$slug}",
            now()->addMinutes(30),
            function () use ($slug) {

                return Tag::query()
                    ->withCount([
                        'posts' => function ($query) {
                            $query
                                ->where('status', 'published')
                                ->whereNotNull('published_at')
                                ->where('published_at', '<=', now());
                        },
                    ])
                    ->where('slug', $slug)
                    ->firstOrFail();
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Get published posts
        |--------------------------------------------------------------------------
        */

        $posts = $tag
            ->posts()
            ->with([
                'category',
                'user',
                'seoMeta',
            ])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where(
                'published_at',
                '<=',
                now()
            )
            ->latest('published_at')
            ->paginate($perPage);


        /*
        |--------------------------------------------------------------------------
        | Return response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            [
                'tag' => new TagResource(
                    $tag
                ),

                'posts' => ApiResponse::nestedPage(PostResource::collection($posts->getCollection()), $posts),
            ],
            'Tag retrieved successfully.'
        );
    }
}
