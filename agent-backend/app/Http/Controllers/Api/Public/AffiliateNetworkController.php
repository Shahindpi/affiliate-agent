<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\AffiliateNetwork;
use Illuminate\Http\Request;

use App\Http\Resources\Api\AffiliateNetworkResource;
use App\Services\CacheService;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Cache;

class AffiliateNetworkController extends Controller
{
    /**
     * List public affiliate networks.
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

        $cacheKey = CacheService::publicAffiliateNetworksKey() . '_' . Cache::get('public_cache_version', 0) . '_' . md5(json_encode($request->query()));

        $networks = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($request, $perPage) {

                $networks = AffiliateNetwork::query()
                    ->where('status', true)
                    ->withCount(['affiliateProducts' => fn ($query) => $query->where('status', true)])

                    /*
                    |--------------------------------------------------------------------------
                    | Search
                    |--------------------------------------------------------------------------
                    */

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

                    /*
                    |--------------------------------------------------------------------------
                    | Sort
                    |--------------------------------------------------------------------------
                    */

                    ->orderBy('name')

                    /*
                    |--------------------------------------------------------------------------
                    | Pagination
                    |--------------------------------------------------------------------------
                    */

                    ->paginate($perPage);

                return $networks;
            });

        return ApiResponse::paginated(AffiliateNetworkResource::collection($networks), 'Affiliate networks retrieved successfully.');
    }

    /**
     * Show a single affiliate network.
     */
    public function show(string $slug)
    {
        /*
        |--------------------------------------------------------------------------
        | Cache Key
        |--------------------------------------------------------------------------
        */

        $cacheKey = CacheService::publicAffiliateNetworkKey($slug) . '_' . Cache::get('public_cache_version', 0);

        $affiliateNetwork = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($slug) {

                $affiliateNetwork = AffiliateNetwork::query()
                    ->where('status', true)
                    ->withCount(['affiliateProducts' => fn ($query) => $query->where('status', true)])
                    ->where('slug', $slug)
                    ->firstOrFail();

                return $affiliateNetwork;
            });

        return ApiResponse::success(new AffiliateNetworkResource($affiliateNetwork), 'Affiliate network retrieved successfully.');
    }
}
