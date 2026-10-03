<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\AffiliateProduct;

use App\Observers\PostObserver;
use App\Observers\CategoryObserver;
use App\Observers\TagObserver;
use App\Observers\AffiliateProductObserver;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | API Rate Limiter
        |--------------------------------------------------------------------------
        */

        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Model Observers
        |--------------------------------------------------------------------------
        */

        Post::observe(PostObserver::class);

        Category::observe(CategoryObserver::class);

        Tag::observe(TagObserver::class);

        AffiliateProduct::observe(AffiliateProductObserver::class);
    }
}