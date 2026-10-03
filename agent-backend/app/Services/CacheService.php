<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    /*
    |--------------------------------------------------------------------------
    | Public Cache Keys
    |--------------------------------------------------------------------------
    */

    public static function publicPostsKey(): string
    {
        return 'public_posts';
    }

    public static function popularPostsKey(): string
    {
        return 'public_popular_posts';
    }

    public static function featuredProductsKey(): string
    {
        return 'public_featured_products';
    }

    public static function homepageKey(): string
    {
        return 'public_homepage';
    }

    public static function publicPostKey(string $slug): string
    {
        return "public_post_{$slug}";
    }

    public static function publicCategoriesKey(): string
    {
        return 'public_categories';
    }

    public static function publicCategoryKey(string $slug): string
    {
        return "public_category_{$slug}";
    }

    public static function publicTagsKey(): string
    {
        return 'public_tags';
    }

    public static function publicTagKey(string $slug): string
    {
        return "public_tag_{$slug}";
    }

    public static function publicProductsKey(): string
    {
        return 'public_products';
    }

    public static function publicProductKey(string $slug): string
    {
        return "public_product_{$slug}";
    }

    public static function publicBrandsKey(): string
    {
        return 'public_brands';
    }

    public static function publicBrandKey(string $slug): string
    {
        return "public_brand_{$slug}";
    }

    public static function publicAffiliateNetworksKey(): string
    {
        return 'public_affiliate_networks';
    }

    public static function publicAffiliateNetworkKey(string $slug): string
    {
        return "public_affiliate_network_{$slug}";
    }

    /*
    |--------------------------------------------------------------------------
    | Search Cache Keys
    |--------------------------------------------------------------------------
    */

    public static function searchKey(string $query): string
    {
        return 'search_' . md5(strtolower(trim($query)));
    }

    public static function searchSuggestionsKey(string $query): string
    {
        return 'search_suggestions_' . Cache::get('public_cache_version', 0) . '_' . md5(strtolower(trim($query)));
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard Cache Keys
    |--------------------------------------------------------------------------
    */

    public static function dashboardOverviewKey(): string
    {
        return 'admin_dashboard_overview';
    }

    public static function dashboardAnalyticsKey(): string
    {
        return 'admin_dashboard_analytics';
    }

    public static function dashboardPopularPostsKey(): string
    {
        return 'admin_dashboard_popular_posts';
    }

    /*
    |--------------------------------------------------------------------------
    | Comment Statistics Cache
    |--------------------------------------------------------------------------
    */

    public static function commentStatisticsKey(): string
    {
        return 'admin_comment_statistics';
    }

    public static function clearCommentStatistics(): void
    {
        Cache::forget(self::commentStatisticsKey());
    }

    public static function newsletterStatisticsKey(): string
    {
        return 'admin_newsletter_statistics';
    }

    /*
    |--------------------------------------------------------------------------
    | Site Settings Cache
    |--------------------------------------------------------------------------
    */

    public static function siteSettingsKey(): string
    {
        return 'public_site_settings';
    }

    public static function clearSiteSettings(): void
    {
        Cache::forget(self::siteSettingsKey());
    }

    /*
    |--------------------------------------------------------------------------
    | Clear Public Cache
    |--------------------------------------------------------------------------
    */

    public static function clearPublicCaches(): void
    {
        Cache::add('public_cache_version', 0);
        Cache::increment('public_cache_version');
        Cache::forget(self::homepageKey());
        
        Cache::forget(self::publicPostsKey());
        Cache::forget(self::popularPostsKey());

        Cache::forget(self::publicCategoriesKey());
        Cache::forget(self::publicTagsKey());

        Cache::forget(self::publicProductsKey());
        Cache::forget(self::featuredProductsKey());

        Cache::forget(self::publicBrandsKey());
        Cache::forget(self::publicAffiliateNetworksKey());

        Cache::forget(self::siteSettingsKey());
        Cache::forget(self::publicHomepageKey());
        
    }

    /*
    |--------------------------------------------------------------------------
    | Homepage Cache
    |--------------------------------------------------------------------------
    */

    public static function publicHomepageKey(): string
    {
        return 'public_homepage';
    }

    public static function clearHomepage(): void
    {
        Cache::forget(self::publicHomepageKey());
    }

    /*
    |--------------------------------------------------------------------------
    | Clear Search Cache
    |--------------------------------------------------------------------------
    */

    public static function clearSearchCaches(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Search caches are query-based (MD5 keys).
        | Since we cannot enumerate every query key, clear the
        | public list caches whenever searchable content changes.
        |--------------------------------------------------------------------------
        */

        self::clearPublicCaches();
    }

    /*
    |--------------------------------------------------------------------------
    | Clear Dashboard Cache
    |--------------------------------------------------------------------------
    */

    public static function clearDashboardCaches(): void
    {
        Cache::forget(self::dashboardOverviewKey());
        Cache::forget(self::dashboardAnalyticsKey());
        Cache::forget(self::dashboardPopularPostsKey());
        Cache::forget(self::newsletterStatisticsKey());
    }

    public static function clearNewsletterCaches(): void
    {
        Cache::forget(self::newsletterStatisticsKey());
    }

    /*
    |--------------------------------------------------------------------------
    | Clear Single Resources
    |--------------------------------------------------------------------------
    */

    public static function clearPost(string $slug): void
    {
        Cache::forget(self::publicPostKey($slug));
    }

    public static function clearCategory(string $slug): void
    {
        Cache::forget(self::publicCategoryKey($slug));
    }

    public static function clearTag(string $slug): void
    {
        Cache::forget(self::publicTagKey($slug));
    }

    public static function clearProduct(string $slug): void
    {
        Cache::forget(self::publicProductKey($slug));
    }

    public static function clearBrand(string $slug): void
    {
        Cache::forget(self::publicBrandKey($slug));
    }

    public static function clearAffiliateNetwork(string $slug): void
    {
        Cache::forget(self::publicAffiliateNetworkKey($slug));
    }

    /*
    |--------------------------------------------------------------------------
    | Clear Resource + Related Caches
    |--------------------------------------------------------------------------
    */

    public static function clearPostCaches(string $slug): void
    {
        self::clearPost($slug);
        self::clearPublicCaches();
        self::clearSearchCaches();
        self::clearDashboardCaches();
    }

    public static function clearCategoryCaches(string $slug): void
    {
        self::clearCategory($slug);
        self::clearPublicCaches();
        self::clearSearchCaches();
        self::clearDashboardCaches();
    }

    public static function clearTagCaches(string $slug): void
    {
        self::clearTag($slug);
        self::clearPublicCaches();
        self::clearSearchCaches();
        self::clearDashboardCaches();
    }

    public static function clearProductCaches(string $slug): void
    {
        self::clearProduct($slug);
        self::clearPublicCaches();
        self::clearSearchCaches();
        self::clearDashboardCaches();
    }

    public static function clearBrandCaches(string $slug): void
    {
        self::clearBrand($slug);
        self::clearPublicCaches();
        self::clearSearchCaches();
        self::clearDashboardCaches();
    }

    public static function clearAffiliateNetworkCaches(string $slug): void
    {
        self::clearAffiliateNetwork($slug);
        self::clearPublicCaches();
        self::clearSearchCaches();
        self::clearDashboardCaches();
    }

    /*
    |--------------------------------------------------------------------------
    | Public Comment Cache Keys
    |--------------------------------------------------------------------------
    */

    public static function publicCommentsKey(string $slug): string
    {
        return "public_comments_{$slug}";
    }

    public static function clearComments(string $slug): void
    {
        Cache::forget(self::publicCommentsKey($slug));
    }

    /*
    |--------------------------------------------------------------------------
    | Clear Everything
    |--------------------------------------------------------------------------
    */

    public static function clearAll(): void
    {
        Cache::flush();
    }
}
