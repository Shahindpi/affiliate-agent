<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\AffiliateProduct;
use App\Models\SiteSetting;

use App\Http\Resources\Api\PostResource;
use App\Http\Resources\Api\CategoryResource;
use App\Http\Resources\Api\TagResource;
use App\Http\Resources\Api\AffiliateProductResource;

use App\Http\Resources\PublicSiteSettingResource;

use App\Services\CacheService;
use App\Support\ApiResponse;

class HomeController extends Controller
{
    /**
     * Homepage API.
     */
    public function index(): JsonResponse
    {
        $home = Cache::remember(

            CacheService::publicHomepageKey(),

            now()->addMinutes(30),

            function () {

                return [

                    'settings' => PublicSiteSettingResource::make(
                        SiteSetting::firstOrCreate(['id' => 1])
                    ),

                    'featured_posts' => PostResource::collection(
                        Post::query()
                            ->published()
                            ->latest('published_at')
                            ->take(5)
                            ->get()
                    ),

                    'latest_posts' => PostResource::collection(
                        Post::query()
                            ->published()
                            ->latest('published_at')
                            ->take(12)
                            ->get()
                    ),

                    'categories' => CategoryResource::collection(
                        Category::query()
                            ->where('status', true)
                            ->orderBy('sort_order')
                            ->get()
                    ),

                    'featured_products' => AffiliateProductResource::collection(
                        AffiliateProduct::query()
                            ->where('status', true)
                            ->where('featured', true)
                            ->latest()
                            ->take(8)
                            ->get()
                    ),

                    'popular_posts' => PostResource::collection(
                        Post::query()
                            ->published()
                            ->orderByDesc('views')
                            ->take(5)
                            ->get()
                    ),

                    'trending_tags' => TagResource::collection(
                        Tag::query()
                            ->withCount('posts')
                            ->orderByDesc('posts_count')
                            ->take(10)
                            ->get()
                    ),

                ];
            }

        );

        return ApiResponse::success(
            $home,
            'Homepage data retrieved successfully.'
        );
    }
}