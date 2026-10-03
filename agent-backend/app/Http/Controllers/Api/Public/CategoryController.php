<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CategoryResource;
use App\Http\Resources\Api\PostResource;
use App\Http\Resources\Api\AffiliateProductResource;
use App\Http\Resources\Api\BrandResource;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Support\ApiResponse;
use App\Http\Resources\Api\Collections\PaginatedApiCollection;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    /**
     * List public categories.
     */
    public function index(Request $request)
    {
        $perPage = min(
            max($request->integer('per_page', 20), 1),
            50
        );

        $cacheKey = 'public_categories_' . Cache::get('public_cache_version', 0) . '_' . md5(
            json_encode($request->query())
        );

        $categories = Cache::remember(
            $cacheKey,
            now()->addMinutes(30),
            function () use ($request, $perPage) {

                return Category::query()
                    ->where('status', true)
                    ->withCount(['posts' => fn ($query) => $query->published()])
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

        return CategoryResource::collection($categories)
            ->additional([
                'success' => true,
            ]);
    }


    /**
     * Show category with published posts.
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
        | Find category
        |--------------------------------------------------------------------------
        */

        $category = Cache::remember(
            "public_category_{$slug}",
            now()->addMinutes(30),
            function () use ($slug) {

                return Category::query()
                    ->with([
                        'seoMeta',
                    ])
                    ->where('status', true)
                    ->where('slug', $slug)
                    ->firstOrFail();
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Get published posts
        |--------------------------------------------------------------------------
        */

        $posts = $category
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

        $products = $category->affiliateProducts()
            ->where('status', true)
            ->with(['brand', 'affiliateNetwork', 'category', 'seoMeta'])
            ->orderByDesc('featured')
            ->latest()
            ->paginate($perPage, ['*'], 'product_page');

        $brands = \App\Models\Brand::query()
            ->where('status', true)
            ->whereHas('affiliateProducts', fn ($query) => $query
                ->where('category_id', $category->id)
                ->where('status', true))
            ->withCount(['affiliateProducts' => fn ($query) => $query
                ->where('category_id', $category->id)
                ->where('status', true)])
            ->orderByDesc('affiliate_products_count')
            ->limit(8)
            ->get();


        /*
        |--------------------------------------------------------------------------
        | Return response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            [
                'category' => new CategoryResource(
                    $category
                ),

                'posts' => ApiResponse::nestedPage(PostResource::collection($posts->getCollection()), $posts),
                'products' => ApiResponse::nestedPage(AffiliateProductResource::collection($products->getCollection()), $products),
                'brands' => BrandResource::collection($brands),
            ],
            'Category retrieved successfully.'
        );
    }
}
