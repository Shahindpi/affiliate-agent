<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\AffiliateProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\ApiResponse;
use App\Services\CacheService;

use App\Http\Resources\Api\AffiliateProductResource;
use App\Http\Resources\Api\PostResource;
use App\Http\Resources\Api\Collections\PaginatedApiCollection;
use Illuminate\Support\Facades\Cache;

class AffiliateProductController extends Controller
{
    /**
     * List published/active affiliate products.
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

        $cacheKey = 'public_products_' . Cache::get('public_cache_version', 0) . '_' . md5(
            json_encode($request->query())
        );

        /*
        |--------------------------------------------------------------------------
        | Get Products (Cached)
        |--------------------------------------------------------------------------
        */

        $products = Cache::remember(
            $cacheKey,
            now()->addHours(6),
            function () use ($request, $perPage) {

                return AffiliateProduct::query()
                    ->where('status', true)
                    ->when($request->filled('brand'), fn ($query) => $query->whereHas('brand', fn ($brand) => $brand->where('slug', $request->input('brand'))))
                    ->when($request->filled('category'), fn ($query) => $query->whereHas('category', fn ($category) => $category->where('slug', $request->input('category'))))
                    ->with([
                        'brand:id,name,slug,logo',
                        'affiliateNetwork:id,name,slug,website',
                        'category:id,name,slug',
                        'seoMeta:id,seoable_id,seoable_type,meta_title,meta_description,canonical_url',
                    ])
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->paginate($perPage);
            }
        );

        return AffiliateProductResource::collection($products)
            ->additional([
                'success' => true,
        ]);
    }

    /**
     * Featured affiliate products.
     */
    public function featured(): JsonResponse
    {
        $products = Cache::remember(
            CacheService::featuredProductsKey(),
            now()->addHours(6),
            function () {

                return AffiliateProduct::query()
                    ->where('status', true)
                    ->where('featured', true)
                    ->with([
                        'brand',
                        'affiliateNetwork',
                        'category',

                        'seoMeta' => function ($query) {
                            $query->with('seoable');
                        },
                    ])
                    ->orderByDesc('rating')
                    ->orderByDesc('updated_at')
                    ->limit(8)
                    ->get();
            }
        );

        return ApiResponse::success(
            AffiliateProductResource::collection($products),
            'Featured products retrieved successfully.'
        );
    }


    /**
     * Show a single affiliate product.
     */
    public function show(string $slug): JsonResponse
    {
        $product = Cache::remember(
            CacheService::publicProductKey($slug),
            now()->addHours(6),
            function () use ($slug) {

                return AffiliateProduct::query()
                    ->where('slug', $slug)
                    ->where('status', true)
                    ->with([
                        'brand',
                        'category',

                        'seoMeta' => function ($query) {
                            $query->with('seoable');
                        },
                    ])
                    ->firstOrFail();
            }
        );

        $relatedProducts = AffiliateProduct::query()
            ->where('status', true)
            ->whereKeyNot($product->id)
            ->when($product->category_id, fn ($query) => $query->where('category_id', $product->category_id))
            ->with(['brand', 'affiliateNetwork', 'category', 'seoMeta'])
            ->orderByDesc('featured')
            ->latest()
            ->limit(4)
            ->get();

        $relatedPosts = $product->posts()
            ->published()
            ->with(['category', 'tags', 'seoMeta'])
            ->latest('published_at')
            ->limit(4)
            ->get();

        return ApiResponse::success(
            [
                'product' => new AffiliateProductResource($product),
                'related_products' => AffiliateProductResource::collection($relatedProducts),
                'related_posts' => PostResource::collection($relatedPosts),
            ],
            'Affiliate product retrieved successfully.'
        );
    }
}
