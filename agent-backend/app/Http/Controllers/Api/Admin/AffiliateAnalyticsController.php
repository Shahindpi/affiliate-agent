<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateEvent;
use App\Models\AffiliateProduct;
use App\Models\Brand;
use App\Models\AffiliateNetwork;
use App\Support\AdminPageSize;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateAnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $demo = $request->boolean('demo');
        $query = AffiliateProduct::query()
            ->with('brand:id,name')
            ->withCount([
                'events as impressions_count' => fn ($events) => $events->where('kind', 'impression')->where('is_demo', $demo),
                'events as clicks_count' => fn ($events) => $events->where('kind', 'click')->where('is_demo', $demo),
            ])->orderByDesc('impressions_count')->orderBy('id');
        $page = $query->paginate(AdminPageSize::resolve($request, $query, 20));
        $page->getCollection()->each(function ($product) {
            $product->setAttribute('ctr', $product->impressions_count > 0
                ? round(100 * $product->clicks_count / $product->impressions_count, 2) : 0);
        });
        $brands = Brand::query()->withCount([
            'affiliateEvents as impressions_count' => fn ($events) => $events->where('kind', 'impression')->where('is_demo', $demo),
            'affiliateEvents as clicks_count' => fn ($events) => $events->where('kind', 'click')->where('is_demo', $demo),
        ])->orderBy('name')->get(['id', 'name']);
        $networks = AffiliateNetwork::query()->withCount([
            'affiliateEvents as impressions_count' => fn ($events) => $events->where('kind', 'impression')->where('is_demo', $demo),
            'affiliateEvents as clicks_count' => fn ($events) => $events->where('kind', 'click')->where('is_demo', $demo),
        ])->orderBy('name')->get(['id', 'name']);
        $placements = AffiliateEvent::query()->where('is_demo', $demo)->selectRaw('placement, kind, COUNT(*) as total')
            ->groupBy('placement', 'kind')->get()->groupBy(fn ($event) => $event->placement ?? 'unknown')
            ->map(fn ($events, $placement) => [
                'placement' => $placement,
                'impressions' => (int) ($events->firstWhere('kind', 'impression')?->total ?? 0),
                'clicks' => (int) ($events->firstWhere('kind', 'click')?->total ?? 0),
            ])->values();
        $daily = AffiliateEvent::query()->where('is_demo', $demo)->selectRaw('DATE(created_at) as date, kind, COUNT(*) as total')
            ->groupByRaw('DATE(created_at), kind')->orderBy('date')->get();
        return ApiResponse::success([
            'source' => $demo ? 'demo' : 'first_party',
            'products' => $page->items(),
            'brands' => $brands,
            'networks' => $networks,
            'placements' => $placements,
            'daily' => $daily,
            'totals' => [
                'impressions' => AffiliateEvent::where('kind', 'impression')->where('is_demo', $demo)->count(),
                'clicks' => AffiliateEvent::where('kind', 'click')->where('is_demo', $demo)->count(),
            ],
            'pagination' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total(),
            ],
        ], 'Affiliate analytics retrieved successfully.');
    }
}
