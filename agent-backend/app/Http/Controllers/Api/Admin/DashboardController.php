<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AffiliateProductResource;
use App\Http\Resources\Api\PostResource;
use App\Models\AffiliateNetwork;
use App\Models\AffiliateProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\CacheService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Admin dashboard statistics.
     */
    public function index(): JsonResponse
    {
        $data = Cache::remember(
            'admin_dashboard_overview',
            now()->addMinutes(5),
            function () {

                return [

                    /*
                    |--------------------------------------------------------------------------
                    | Overview Cards
                    |--------------------------------------------------------------------------
                    */

                    'overview' => [
                        'posts' => Post::count(),

                        'published_posts' => Post::where(
                            'status',
                            'published'
                        )->count(),

                        'draft_posts' => Post::where(
                            'status',
                            'draft'
                        )->count(),

                        'categories' => Category::count(),

                        'tags' => Tag::count(),

                        'affiliate_products' => AffiliateProduct::count(),

                        'active_products' => AffiliateProduct::where(
                            'status',
                            true
                        )->count(),

                        'users' => User::count(),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Recent Posts
                    |--------------------------------------------------------------------------
                    */

                    'recent_posts' => Post::query()
                        ->select([
                            'id',
                            'title',
                            'slug',
                            'status',
                            'published_at',
                            'created_at',
                        ])
                        ->latest()
                        ->limit(5)
                        ->get(),

                    /*
                    |--------------------------------------------------------------------------
                    | Top Categories
                    |--------------------------------------------------------------------------
                    */

                    'top_categories' => Category::query()
                        ->withCount('posts')
                        ->orderByDesc('posts_count')
                        ->limit(5)
                        ->get([
                            'id',
                            'name',
                            'slug',
                        ]),
                ];
            }
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Dashboard analytics.
     */
    public function analytics(): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Posts published during the last 12 months
        |--------------------------------------------------------------------------
        */

        $monthExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', published_at)",
            'pgsql' => "to_char(published_at, 'YYYY-MM')",
            default => "DATE_FORMAT(published_at, '%Y-%m')",
        };

        $monthlyPosts = Post::query()
            ->selectRaw("{$monthExpression} as month, COUNT(*) as total")
            ->published()
            ->where('published_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        $postsPerMonth = collect(range(0, 11))->map(function ($i) use ($monthlyPosts) {
            $month = now()->subMonths(11 - $i)->format('Y-m');

            return [
                'month' => $month,
                'posts' => (int) ($monthlyPosts[$month] ?? 0),
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | Posts by status
        |--------------------------------------------------------------------------
        */

        $postStatus = Post::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Products grouped by category
        |--------------------------------------------------------------------------
        */

        $productsByCategory = Category::query()
            ->withCount('affiliateProducts')
            ->orderByDesc('affiliate_products_count')
            ->limit(10)
            ->get([
                'id',
                'name',
                'slug',
            ]);

        $data = Cache::remember(
            'admin_dashboard_analytics',
            now()->addMinutes(5),
            function () use (
                $postsPerMonth,
                $postStatus,
                $productsByCategory
            ) {

                return [
                    'posts_per_month' => $postsPerMonth,
                    'posts_by_status' => $postStatus,
                    'products_by_category' => $productsByCategory,
                ];
            }
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Health check for admin dashboard.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,

            'data' => [

                /*
                |--------------------------------------------------------------------------
                | Application
                |--------------------------------------------------------------------------
                */

                'app' => [
                    'name' => config('app.name'),
                    'environment' => app()->environment(),
                    'debug' => config('app.debug'),
                    'timezone' => config('app.timezone'),
                    'laravel_version' => app()->version(),
                    'php_version' => PHP_VERSION,
                ],

                /*
                |--------------------------------------------------------------------------
                | Database
                |--------------------------------------------------------------------------
                */

                'database' => [
                    'connection' => config('database.default'),
                    'status' => 'connected',
                ],

                /*
                |--------------------------------------------------------------------------
                | Cache
                |--------------------------------------------------------------------------
                */

                'cache' => [
                    'driver' => config('cache.default'),
                    'status' => 'enabled',
                ],

                /*
                |--------------------------------------------------------------------------
                | Storage
                |--------------------------------------------------------------------------
                */

                'storage' => [
                    'public_disk' => is_dir(storage_path('app/public')),
                ],

                /*
                |--------------------------------------------------------------------------
                | Server Time
                |--------------------------------------------------------------------------
                */

                'server_time' => now()->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Dashboard overview.
     */
    public function overview(): JsonResponse
    {
        $overview = Cache::remember(
            CacheService::dashboardOverviewKey(),
            now()->addHours(2),
            function () {

                return [

                    'statistics' => [

                        'posts' => Post::count(),

                        'published_posts' => Post::where(
                            'status',
                            'published'
                        )->count(),

                        'draft_posts' => Post::where(
                            'status',
                            'draft'
                        )->count(),

                        'categories' => Category::count(),

                        'tags' => Tag::count(),

                        'products' => AffiliateProduct::count(),

                        'featured_products' => AffiliateProduct::where(
                            'featured',
                            true
                        )->count(),

                        'brands' => Brand::count(),

                        'affiliate_networks' => AffiliateNetwork::count(),

                        'users' => User::count(),
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | Latest Posts
                    |--------------------------------------------------------------------------
                    */

                    'recent_posts' => PostResource::collection(
                        Post::with('category')
                            ->latest()
                            ->limit(5)
                            ->get()
                    ),

                    /*
                    |--------------------------------------------------------------------------
                    | Latest Affiliate Products
                    |--------------------------------------------------------------------------
                    */

                    'recent_products' => AffiliateProductResource::collection(
                        AffiliateProduct::with([
                            'brand',
                            'category',
                        ])
                            ->latest()
                            ->limit(5)
                            ->get()
                    ),
                ];
            }
        );

        return ApiResponse::success(
            $overview,
            'Dashboard overview retrieved successfully.'
        );
    }

    /**
     * Popular posts analytics.
     */
    public function popularPosts(): JsonResponse
    {
        $posts = Cache::remember(
            CacheService::dashboardPopularPostsKey(),
            now()->addHours(2),
            function () {

                return Post::query()
                    ->where('status', 'published')
                    ->with('category')
                    ->orderByDesc('views')
                    ->orderByDesc('published_at')
                    ->limit(10)
                    ->get();
            }
        );

        return ApiResponse::success(
            PostResource::collection($posts),
            'Popular posts analytics retrieved successfully.'
        );
    }
}
