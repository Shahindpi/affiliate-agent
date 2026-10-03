<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPageSize;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Http\Resources\Api\BrandResource;
use App\Support\ApiResponse;
use App\Services\CacheService;

class BrandController extends Controller
{
    /**
     * Display a listing of brands.
     */
    public function index(Request $request)
    {
        $query = Brand::query()
            ->withCount('affiliateProducts')
            ->latest();

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = $request->string('search');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $perPage = AdminPageSize::resolve($request, $query, 15);

        $brands = $query->paginate($perPage);

        return ApiResponse::paginated(
            BrandResource::collection($brands),
            'Brands retrieved successfully.'
        );
    }

    /**
     * Store a newly created brand.
     */
    public function store(StoreBrandRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Generate Slug
        |--------------------------------------------------------------------------
        */

        $validated['slug'] = $validated['slug']
            ?? Str::slug($validated['name']);

        $brand = Brand::create($validated);

        CacheService::clearPublicCaches();
        CacheService::clearDashboardCaches();

        return ApiResponse::success(
            new BrandResource($brand),
            'Brand created successfully.',
            201
        );
    }

    /**
     * Display the specified brand.
     */
    public function show(Brand $brand): JsonResponse
    {
        $brand->loadCount('affiliateProducts');

        return ApiResponse::success(
            new BrandResource($brand),
            'Brand retrieved successfully.'
        );
    }

    /**
     * Update the specified brand.
     */
    public function update(
        UpdateBrandRequest $request,
        Brand $brand
    ): JsonResponse {

        $validated = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Auto-generate slug when name changes.
        |--------------------------------------------------------------------------
        */

        if (
            isset($validated['name']) &&
            !isset($validated['slug'])
        ) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $brand->update($validated);

        CacheService::clearPublicCaches();
        CacheService::clearDashboardCaches();

        return ApiResponse::success(
            new BrandResource(
                $brand->fresh()->loadCount('affiliateProducts')
            ),
            'Brand updated successfully.'
        );
    }

    /**
     * Remove the specified brand.
     */
    public function destroy(Brand $brand): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Prevent deleting brands that still have products.
        |--------------------------------------------------------------------------
        */

        if ($brand->affiliateProducts()->exists()) {
            return ApiResponse::error(
                'Cannot delete a brand containing affiliate products.',
                null,
                422
            );
        }

        $brand->delete();

        CacheService::clearPublicCaches();
        CacheService::clearDashboardCaches();

        return ApiResponse::success(
            null,
            'Brand deleted successfully.'
        );
    }
}