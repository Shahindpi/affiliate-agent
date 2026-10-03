<?php

namespace App\Http\Controllers\Api\Public;

use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\Post;
use App\Models\AffiliateProduct;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Brand;

use App\Http\Resources\Api\PostResource;
use App\Http\Resources\Api\AffiliateProductResource;
use App\Http\Resources\Api\CategoryResource;
use App\Http\Resources\Api\TagResource;
use App\Http\Resources\Api\BrandResource;
use App\Services\CacheService;

use App\Support\ApiResponse;

class SearchController extends Controller
{
    /**
     * Global search endpoint.
     */
    public function index(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));

        if ($query === '') {
            return ApiResponse::success([
                'query' => '',
                'posts' => [],
                'products' => [],
                'categories' => [],
                'tags' => [],
                'brands' => [],
            ], 'Search completed successfully.');
        }

        /*
        |--------------------------------------------------------------------------
        | Posts
        |--------------------------------------------------------------------------
        */

        $posts = Post::query()
            ->published()
            ->where(function ($builder) use ($query) {
                $builder
                    ->where('title', 'like', "%{$query}%")
                    ->orWhere('excerpt', 'like', "%{$query}%");
            })
            ->with([
                'category',
                'tags',
                'seoMeta.seoable',
            ])
            ->limit(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Affiliate Products
        |--------------------------------------------------------------------------
        */

        $products = AffiliateProduct::query()
            ->where('status', true)
            ->where(function ($builder) use ($query) {
                $builder
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('short_description', 'like', "%{$query}%");
            })
            ->with([
                'brand',
                'category',
                'seoMeta.seoable',
            ])
            ->limit(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        */

        $categories = Category::query()
            ->where('status', true)
            ->where('name', 'like', "%{$query}%")
            ->limit(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Tags
        |--------------------------------------------------------------------------
        */

        $tags = Tag::query()
            ->where('name', 'like', "%{$query}%")
            ->limit(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Brands
        |--------------------------------------------------------------------------
        */

        $brands = Brand::query()
            ->where('status', true)
            ->where('name', 'like', "%{$query}%")
            ->limit(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success([
            'query' => $query,

            'posts' => PostResource::collection($posts),

            'products' => AffiliateProductResource::collection($products),

            'categories' => CategoryResource::collection($categories),

            'tags' => TagResource::collection($tags),

            'brands' => BrandResource::collection($brands),
        ], 'Search completed successfully.');
    }

    /**
     * Search suggestions for autocomplete.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));

        if (strlen($query) < 2) {
            return ApiResponse::success([
                'query' => $query,
                'suggestions' => [],
            ], 'Suggestions retrieved successfully.');
        }

        $cacheKey = CacheService::searchSuggestionsKey($query);

        $suggestions = Cache::remember(
            $cacheKey,
            now()->addHours(6),
            function () use ($query) {

                $results = collect();

                /*
                |--------------------------------------------------------------------------
                | Posts
                |--------------------------------------------------------------------------
                */

                Post::query()
                    ->published()
                    ->where('title', 'like', "%{$query}%")
                    ->limit(5)
                    ->get([
                        'title',
                        'slug',
                    ])
                    ->each(function ($post) use ($results) {
                        $results->push([
                            'type' => 'post',
                            'title' => $post->title,
                            'slug' => $post->slug,
                            'url' => "/posts/{$post->slug}",
                        ]);
                    });

                /*
                |--------------------------------------------------------------------------
                | Affiliate Products
                |--------------------------------------------------------------------------
                */

                AffiliateProduct::query()
                    ->where('status', true)
                    ->where('name', 'like', "%{$query}%")
                    ->limit(5)
                    ->get([
                        'name',
                        'slug',
                    ])
                    ->each(function ($product) use ($results) {
                        $results->push([
                            'type' => 'product',
                            'title' => $product->name,
                            'slug' => $product->slug,
                            'url' => "/products/{$product->slug}",
                        ]);
                    });

                /*
                |--------------------------------------------------------------------------
                | Categories
                |--------------------------------------------------------------------------
                */

                Category::query()
                    ->where('status', true)
                    ->where('name', 'like', "%{$query}%")
                    ->limit(3)
                    ->get([
                        'name',
                        'slug',
                    ])
                    ->each(function ($category) use ($results) {
                        $results->push([
                            'type' => 'category',
                            'title' => $category->name,
                            'slug' => $category->slug,
                            'url' => "/categories/{$category->slug}",
                        ]);
                    });

                /*
                |--------------------------------------------------------------------------
                | Tags
                |--------------------------------------------------------------------------
                */

                Tag::query()
                    ->where('name', 'like', "%{$query}%")
                    ->limit(3)
                    ->get([
                        'name',
                        'slug',
                    ])
                    ->each(function ($tag) use ($results) {
                        $results->push([
                            'type' => 'tag',
                            'title' => $tag->name,
                            'slug' => $tag->slug,
                            'url' => "/tags/{$tag->slug}",
                        ]);
                    });

                /*
                |--------------------------------------------------------------------------
                | Brands
                |--------------------------------------------------------------------------
                */

                Brand::query()
                    ->where('status', true)
                    ->where('name', 'like', "%{$query}%")
                    ->limit(3)
                    ->get([
                        'name',
                        'slug',
                    ])
                    ->each(function ($brand) use ($results) {
                        $results->push([
                            'type' => 'brand',
                            'title' => $brand->name,
                            'slug' => $brand->slug,
                            'url' => "/brands/{$brand->slug}",
                        ]);
                    });

                return $results
                    ->take(10)
                    ->values();
            }
        );

        return ApiResponse::success([
            'query' => $query,
            'suggestions' => $suggestions,
        ], 'Suggestions retrieved successfully.');
    }
}
