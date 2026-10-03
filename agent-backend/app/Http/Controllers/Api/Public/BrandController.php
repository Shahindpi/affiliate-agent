<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;

use App\Http\Resources\Api\BrandResource;
use App\Http\Resources\Api\AffiliateProductResource;
use App\Support\ApiResponse;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;

class BrandController extends Controller
{
    /**
     * List public brands.
     */
    public function index(Request $request)
    {
        $perPage = min(
            max($request->integer('per_page', 10), 1),
            50
        );

        $cacheKey = CacheService::publicBrandsKey() . '_' . Cache::get('public_cache_version', 0) . '_' . md5(json_encode($request->query()));

        $brands = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($request, $perPage) {

                $brands = Brand::query()
                    ->where('status', true)
                    ->withCount(['affiliateProducts' => fn ($query) => $query->where('status', true)])

                    ->when(
                        $request->filled('search'),
                        function ($query) use ($request) {

                            $search = $request->string('search');

                            $query->where(function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%")
                                  ->orWhere(
                                      'description',
                                      'like',
                                      "%{$search}%"
                                  );
                            });
                        }
                    )

                    ->orderBy('name')
                    ->paginate($perPage);

                return $brands;
            });

        return ApiResponse::paginated(BrandResource::collection($brands), 'Brands retrieved successfully.');
    }

    /**
     * Show a single public brand.
     */
    public function show(string $slug)
    {
        $cacheKey = CacheService::publicBrandKey($slug) . '_' . Cache::get('public_cache_version', 0);

        $brand = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($slug) {

                $brand = Brand::query()
                    ->where('status', true)
                    ->withCount(['affiliateProducts' => fn ($query) => $query->where('status', true)])
                    ->where('slug', $slug)
                    ->firstOrFail();

                return $brand;
            });

        $products = $brand->affiliateProducts()
            ->where('status', true)
            ->with(['brand', 'affiliateNetwork', 'category', 'seoMeta'])
            ->orderByDesc('featured')
            ->latest()
            ->paginate(12);

        return ApiResponse::success([
            'brand' => new BrandResource($brand),
            'products' => ApiResponse::nestedPage(AffiliateProductResource::collection($products->getCollection()), $products),
        ], 'Brand retrieved successfully.');
    }
}
