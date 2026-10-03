<?php

use App\Http\Controllers\Api\Admin\AffiliateNetworkController;
use App\Http\Controllers\Api\Admin\AffiliateProductController;
use App\Http\Controllers\Api\Admin\AffiliateAnalyticsController;
use App\Http\Controllers\Api\Admin\BrandController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\CommentController as AdminCommentController;
use App\Http\Controllers\Api\Admin\ContactMessageController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\HeroBannerController;
use App\Http\Controllers\Api\Admin\MediaController;
use App\Http\Controllers\Api\Admin\NewsletterSubscriberController;
use App\Http\Controllers\Api\Admin\PostController;
use App\Http\Controllers\Api\Admin\ProfileController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\SeoMetaController;
use App\Http\Controllers\Api\Admin\SiteSettingController;
use App\Http\Controllers\Api\Admin\TagController;
use App\Http\Controllers\Api\Admin\UploadController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Public\AffiliateNetworkController as PublicAffiliateNetworkController;
use App\Http\Controllers\Api\Public\AffiliateProductController as PublicAffiliateProductController;
use App\Http\Controllers\Api\Public\AffiliateEventController;
use App\Http\Controllers\Api\Public\BrandController as PublicBrandController;
use App\Http\Controllers\Api\Public\CategoryController as PublicCategoryController;
use App\Http\Controllers\Api\Public\CommentController;
use App\Http\Controllers\Api\Public\ContactController;
use App\Http\Controllers\Api\Public\HomeController;
use App\Http\Controllers\Api\Public\HomepageController;
use App\Http\Controllers\Api\Public\NewsletterController;
use App\Http\Controllers\Api\Public\PostController as PublicPostController;
use App\Http\Controllers\Api\Public\SearchController;
use App\Http\Controllers\Api\Public\SiteSettingController as PublicSiteSettingController;
use App\Http\Controllers\Api\Public\TagController as PublicTagController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Version 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication (Public)
    |--------------------------------------------------------------------------
    */

    Route::post('/auth/login', [
        AuthController::class,
        'login',
    ])->middleware('throttle:login');

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    */

    Route::middleware('throttle:api')
        ->prefix('public')
        ->group(function () {
            /*
            |--------------------------------------------------------------------------
            | Post Comments
            |--------------------------------------------------------------------------
            */

            Route::get('/posts/{slug}/comments', [
                CommentController::class,
                'index',
            ]);

            Route::post('/posts/{slug}/comments', [
                CommentController::class,
                'store',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Contact Form
            |--------------------------------------------------------------------------
            */

            Route::post('/contact', [
                ContactController::class,
                'store',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Public Site Settings
            |--------------------------------------------------------------------------
            */

            Route::get('/settings', [
                PublicSiteSettingController::class,
                'show',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Search
            |--------------------------------------------------------------------------
            */

            Route::get('/search', [
                SearchController::class,
                'index',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Search Suggestions
            |--------------------------------------------------------------------------
            */

            Route::get('/search/suggestions', [
                SearchController::class,
                'suggestions',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Homepage
            |--------------------------------------------------------------------------
            */

            Route::get('/home', [
                HomeController::class,
                'index',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Homepage
            |--------------------------------------------------------------------------
            */

            Route::get('/homepage', [
                HomepageController::class,
                'index',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Popular Posts
            |--------------------------------------------------------------------------
            */

            Route::get('/posts/popular', [
                PublicPostController::class,
                'popular',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Posts
            |--------------------------------------------------------------------------
            */

            Route::get('/posts', [
                PublicPostController::class,
                'index',
            ]);

            Route::get('/posts/{slug}', [
                PublicPostController::class,
                'show',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Categories
            |--------------------------------------------------------------------------
            */

            Route::get('/categories', [
                PublicCategoryController::class,
                'index',
            ]);

            Route::get('/categories/{slug}', [
                PublicCategoryController::class,
                'show',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Tags
            |--------------------------------------------------------------------------
            */

            Route::get('/tags', [
                PublicTagController::class,
                'index',
            ]);

            Route::get('/tags/{slug}', [
                PublicTagController::class,
                'show',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Featured Products
            |--------------------------------------------------------------------------
            */

            Route::post('/affiliate-events', [AffiliateEventController::class, 'store']);

            Route::get('/products/featured', [
                PublicAffiliateProductController::class,
                'featured',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Affiliate Products
            |--------------------------------------------------------------------------
            */

            Route::get('/products', [
                PublicAffiliateProductController::class,
                'index',
            ]);

            Route::get('/products/{slug}', [
                PublicAffiliateProductController::class,
                'show',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Brands
            |--------------------------------------------------------------------------
            */

            Route::get('/brands', [PublicBrandController::class, 'index']);

            Route::get('/brands/{slug}', [PublicBrandController::class, 'show']);

            /*
            |--------------------------------------------------------------------------
            | Affiliate Networks
            |--------------------------------------------------------------------------
            */

            Route::get('/affiliate-networks', [
                PublicAffiliateNetworkController::class,
                'index',
            ]);

            Route::get('/affiliate-networks/{slug}', [
                PublicAffiliateNetworkController::class,
                'show',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Newsletter
            |--------------------------------------------------------------------------
            */

            Route::post('/newsletter/subscribe', [
                NewsletterController::class,
                'subscribe',
            ]);

            Route::post('/newsletter/unsubscribe', [
                NewsletterController::class,
                'unsubscribe',
            ]);

        });

    /*
    |--------------------------------------------------------------------------
    | Authenticated API Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Authenticated User
        |--------------------------------------------------------------------------
        */

        Route::get('/auth/me', [
            AuthController::class,
            'me',
        ]);

        Route::post('/auth/logout', [
            AuthController::class,
            'logout',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Admin API Routes
        |--------------------------------------------------------------------------
        */

        Route::middleware('admin')
            ->prefix('admin')
            ->group(function () {

                /*
                |--------------------------------------------------------------------------
                | Site Settings
                |--------------------------------------------------------------------------
                */

                Route::get('/settings', [
                    SiteSettingController::class,
                    'show',
                ]);

                Route::put('/settings/homepage', [SiteSettingController::class, 'updateHomepage']);

                Route::put('/settings', [
                    SiteSettingController::class,
                    'update',
                ]);

                Route::post('/settings/assets', [
                    SiteSettingController::class,
                    'uploadAssets',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Comment Moderation
                |--------------------------------------------------------------------------
                */

                Route::get('/comments', [
                    AdminCommentController::class,
                    'index',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Comment Statistics
                |--------------------------------------------------------------------------
                */

                Route::get('/comments/statistics', [
                    AdminCommentController::class,
                    'statistics',
                ]);

                Route::get('/comments/{comment}', [
                    AdminCommentController::class,
                    'show',
                ]);

                Route::patch('/comments/{comment}/approve', [
                    AdminCommentController::class,
                    'approve',
                ]);

                Route::patch('/comments/{comment}/spam', [
                    AdminCommentController::class,
                    'spam',
                ]);

                Route::patch('/comments/{comment}/reject', [
                    AdminCommentController::class,
                    'reject',
                ]);

                Route::delete('/comments/{comment}', [
                    AdminCommentController::class,
                    'destroy',
                ]);
                /*
                |--------------------------------------------------------------------------
                | Contact Messages
                |--------------------------------------------------------------------------
                */

                Route::get('/contact/messages', [
                    ContactMessageController::class,
                    'index',
                ]);

                Route::get('/contact/messages/{message}', [
                    ContactMessageController::class,
                    'show',
                ]);

                Route::patch('/contact/messages/{message}/read', [
                    ContactMessageController::class,
                    'markRead',
                ]);

                Route::patch('/contact/messages/{message}/archive', [
                    ContactMessageController::class,
                    'archive',
                ]);

                Route::patch('/contact/messages/{message}/restore', [
                    ContactMessageController::class,
                    'restore',
                ]);

                Route::delete('/contact/messages/{message}', [
                    ContactMessageController::class,
                    'destroy',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Newsletter Subscribers
                |--------------------------------------------------------------------------
                */

                Route::get('/newsletter/subscribers', [
                    NewsletterSubscriberController::class,
                    'index',
                ]);

                Route::get('/newsletter/subscribers/{subscriber}', [
                    NewsletterSubscriberController::class,
                    'show',
                ]);

                Route::patch('/newsletter/subscribers/{subscriber}/status', [
                    NewsletterSubscriberController::class,
                    'updateStatus',
                ]);

                Route::delete('/newsletter/subscribers/{subscriber}', [
                    NewsletterSubscriberController::class,
                    'destroy',
                ]);

                Route::get('/newsletter/statistics', [
                    NewsletterSubscriberController::class,
                    'statistics',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Dashboard
                |--------------------------------------------------------------------------
                */

                Route::get('/dashboard', [
                    DashboardController::class,
                    'index',
                ]);

                Route::get('/dashboard/analytics', [
                    DashboardController::class,
                    'analytics',
                ]);

                Route::get('/dashboard/health', [
                    DashboardController::class,
                    'health',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Admin Profile
                |--------------------------------------------------------------------------
                */

                Route::get('/profile', [
                    ProfileController::class,
                    'show',
                ]);

                Route::put('/profile', [
                    ProfileController::class,
                    'update',
                ]);

                Route::put('/profile/password', [
                    ProfileController::class,
                    'changePassword',
                ]);

                Route::post('/profile/avatar', [
                    ProfileController::class,
                    'uploadAvatar',
                ]);

                Route::put('/users/{user}/password', [UserController::class, 'resetPassword']);
                Route::apiResource('users', UserController::class);
                Route::apiResource('roles', RoleController::class);

                /*
                |--------------------------------------------------------------------------
                | Media Library
                |--------------------------------------------------------------------------
                */

                Route::get('/media', [
                    MediaController::class,
                    'index',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Uploads
                |--------------------------------------------------------------------------
                */

                Route::post('/upload/image', [
                    UploadController::class,
                    'image',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Media Upload
                |--------------------------------------------------------------------------
                */

                Route::post('/media/upload', [
                    MediaController::class,
                    'upload',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Replace Image
                |--------------------------------------------------------------------------
                */

                Route::post('/media/replace', [
                    MediaController::class,
                    'replace',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Delete Image
                |--------------------------------------------------------------------------
                */

                Route::delete('/media/delete', [
                    MediaController::class,
                    'destroy',
                ]);

                Route::get('/affiliate-analytics', [AffiliateAnalyticsController::class, 'index']);

                Route::get('/dashboard/overview', [
                    DashboardController::class,
                    'overview',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Popular Posts Analytics
                |--------------------------------------------------------------------------
                */

                Route::get('/dashboard/popular-posts', [
                    DashboardController::class,
                    'popularPosts',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Posts
                |--------------------------------------------------------------------------
                */

                Route::put('/posts/{post}/tags', [
                    PostController::class,
                    'syncTags',
                ]);

                Route::put('/posts/{post}/affiliate-products', [
                    PostController::class,
                    'syncAffiliateProducts',
                ]);

                Route::apiResource('posts', PostController::class);

                Route::apiResource('hero-banners', HeroBannerController::class)->parameters(['hero-banners' => 'heroBanner']);

                /*
                |--------------------------------------------------------------------------
                | Categories
                |--------------------------------------------------------------------------
                */

                Route::apiResource('categories', CategoryController::class);

                /*
                |--------------------------------------------------------------------------
                | Tags
                |--------------------------------------------------------------------------
                */

                Route::apiResource('tags', TagController::class);

                /*
                |--------------------------------------------------------------------------
                | Affiliate Products
                |--------------------------------------------------------------------------
                */

                Route::apiResource(
                    'affiliate-products',
                    AffiliateProductController::class
                )->parameters(['affiliate-products' => 'affiliateProduct']);

                /*
                |--------------------------------------------------------------------------
                | SEO Metadata
                |--------------------------------------------------------------------------
                */

                Route::put('/posts/{post}/seo', [
                    SeoMetaController::class,
                    'updatePostSeo',
                ]);

                Route::delete('/posts/{post}/seo', [
                    SeoMetaController::class,
                    'destroyPostSeo',
                ]);

                Route::put('/affiliate-products/{affiliateProduct}/seo', [
                    SeoMetaController::class,
                    'updateAffiliateProductSeo',
                ]);

                Route::delete('/affiliate-products/{affiliateProduct}/seo', [
                    SeoMetaController::class,
                    'destroyAffiliateProductSeo',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Brands
                |--------------------------------------------------------------------------
                */

                Route::apiResource('brands', BrandController::class);

                /*
                |--------------------------------------------------------------------------
                | Affiliate Networks
                |--------------------------------------------------------------------------
                */

                Route::apiResource(
                    'affiliate-networks',
                    AffiliateNetworkController::class
                )->parameters(['affiliate-networks' => 'affiliateNetwork']);

            });
    });
});
