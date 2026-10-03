<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateProduct;
use App\Models\Post;
use App\Models\SeoMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Requests\UpdateSeoMetaRequest;
use App\Services\CacheService;
use App\Support\ApiResponse;
use App\Http\Resources\Api\PostResource;

class SeoMetaController extends Controller
{
    /**
     * Update SEO metadata for a post.
     */
    public function updatePostSeo(
        UpdateSeoMetaRequest $request,
        Post $post
    ) {

        $validated = $request->validated();

        $seoMeta = $post->seoMeta()->updateOrCreate(
            [
                'seoable_type' => Post::class,
                'seoable_id' => $post->id,
            ],
            $validated
        );

        CacheService::clearPost($post->slug);


        return ApiResponse::success(
            $seoMeta,
            'Post SEO metadata updated successfully.'
        );
    }


    /**
     * Update SEO metadata for an affiliate product.
     */
    public function updateAffiliateProductSeo(
        Request $request,
        AffiliateProduct $affiliateProduct
    ) {

        $validated = $request->validate([
            'meta_title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'meta_description' => [
                'nullable',
                'string',
                'max:500',
            ],

            'canonical_url' => [
                'nullable',
                'url',
                'max:2048',
            ],

            'og_title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'og_description' => [
                'nullable',
                'string',
                'max:500',
            ],

            'og_image' => [
                'nullable',
                'string',
                'max:2048',
            ],

            'twitter_title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'twitter_description' => [
                'nullable',
                'string',
                'max:500',
            ],

            'twitter_image' => [
                'nullable',
                'string',
                'max:2048',
            ],

            'robots' => [
                'nullable',
                'string',
                'max:100',
            ],

            'schema_data' => [
                'nullable',
                'array',
            ],
        ]);

        $seoMeta = $affiliateProduct
            ->seoMeta()
            ->updateOrCreate(
                [
                    'seoable_type' =>
                        AffiliateProduct::class,

                    'seoable_id' =>
                        $affiliateProduct->id,
                ],
                $validated
            );

        CacheService::clearProduct($affiliateProduct->slug);


        return ApiResponse::success(
            $seoMeta,
            'Affiliate product SEO metadata updated successfully.'
        );
    }


    /**
     * Delete SEO metadata from a post.
     */
    public function destroyPostSeo(
        Post $post
    ) {

        $seoMeta = $post->seoMeta;

        if ($seoMeta) {
            $seoMeta->delete();
        }

        /*
        |--------------------------------------------------------------------------
        | Clear Cache
        |--------------------------------------------------------------------------
        */

        CacheService::clearPost($post->slug);

        return ApiResponse::success(
            null,
            'Post SEO metadata deleted successfully.'
        );
    }

    /**
     * Delete SEO metadata from an affiliate product.
     */
    public function destroyAffiliateProductSeo(
        AffiliateProduct $affiliateProduct
    ) {

        $seoMeta = $affiliateProduct->seoMeta;

        if ($seoMeta) {
            $seoMeta->delete();
        }

        CacheService::clearProduct($affiliateProduct->slug);

        return ApiResponse::success(
            null,
            'Affiliate product SEO metadata deleted successfully.'
        );
    }
}
