<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPageSize;
use App\Models\AffiliateNetwork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Http\Requests\StoreAffiliateNetworkRequest;
use App\Http\Requests\UpdateAffiliateNetworkRequest;

use App\Http\Resources\Api\AffiliateNetworkResource;

use App\Services\CacheService;
use App\Support\ApiResponse;

class AffiliateNetworkController extends Controller
{
    /**
     * Display a listing of affiliate networks.
     */
    public function index(Request $request)
    {
        $query = AffiliateNetwork::query()
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

        $networks = $query->paginate($perPage);

        return ApiResponse::paginated(
            AffiliateNetworkResource::collection($networks),
            'Affiliate networks retrieved successfully.'
        );
    }

    /**
     * Store a newly created affiliate network.
     */
    public function store(
        StoreAffiliateNetworkRequest $request
    ): JsonResponse {

        $validated = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Generate Slug
        |--------------------------------------------------------------------------
        */

        $validated['slug'] = $validated['slug']
            ?? Str::slug($validated['name']);

        $network = AffiliateNetwork::create($validated);

        CacheService::clearPublicCaches();
        CacheService::clearDashboardCaches();

        return ApiResponse::success(
            new AffiliateNetworkResource($network),
            'Affiliate network created successfully.',
            201
        );
    }

    /**
     * Display the specified affiliate network.
     */
    public function show(
        AffiliateNetwork $affiliateNetwork
    ): JsonResponse {

        $affiliateNetwork->loadCount('affiliateProducts');

        return ApiResponse::success(
            new AffiliateNetworkResource($affiliateNetwork),
            'Affiliate network retrieved successfully.'
        );
    }

    /**
     * Update the specified affiliate network.
     */
    public function update(
        UpdateAffiliateNetworkRequest $request,
        AffiliateNetwork $affiliateNetwork
    ): JsonResponse {

        $validated = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Generate Slug Automatically
        |--------------------------------------------------------------------------
        */

        if (
            isset($validated['name']) &&
            !isset($validated['slug'])
        ) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $affiliateNetwork->update($validated);

        CacheService::clearPublicCaches();
        CacheService::clearDashboardCaches();

        return ApiResponse::success(
            new AffiliateNetworkResource(
                $affiliateNetwork
                    ->fresh()
                    ->loadCount('affiliateProducts')
            ),
            'Affiliate network updated successfully.'
        );
    }

    /**
     * Remove the specified affiliate network.
     */
    public function destroy(
        AffiliateNetwork $affiliateNetwork
    ): JsonResponse {

        /*
        |--------------------------------------------------------------------------
        | Prevent Delete When Products Exist
        |--------------------------------------------------------------------------
        */

        if ($affiliateNetwork->affiliateProducts()->exists()) {

            return ApiResponse::error(
                'Cannot delete an affiliate network containing affiliate products.',
                null,
                422
            );
        }

        CacheService::clearPublicCaches();
        CacheService::clearDashboardCaches();

        $affiliateNetwork->delete();

        return ApiResponse::success(
            null,
            'Affiliate network deleted successfully.'
        );
    }
}