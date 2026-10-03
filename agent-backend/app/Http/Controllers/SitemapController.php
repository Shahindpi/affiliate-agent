<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\AffiliateProduct;
use App\Models\Brand;

class SitemapController extends Controller
{
    /**
     * Generate XML sitemap.
     */
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | Published Posts
        |--------------------------------------------------------------------------
        */

        $posts = Post::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('updated_at')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        */

        $categories = Category::query()
            ->orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Tags
        |--------------------------------------------------------------------------
        */

        $tags = Tag::query()
            ->orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Affiliate Products
        |--------------------------------------------------------------------------
        */

        $products = AffiliateProduct::query()
            ->where('status', true)
            ->latest('updated_at')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Brands
        |--------------------------------------------------------------------------
        */

        $brands = Brand::query()
            ->orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Homepage Last Modified
        |--------------------------------------------------------------------------
        */

        $lastModified = collect([
            Post::max('updated_at'),
            Category::max('updated_at'),
            Tag::max('updated_at'),
            AffiliateProduct::max('updated_at'),
            Brand::max('updated_at'),
        ])
            ->filter()
            ->max();

        /*
        |--------------------------------------------------------------------------
        | Return XML Sitemap
        |--------------------------------------------------------------------------
        */

        return response()
            ->view('sitemap.index', compact(
                'posts',
                'categories',
                'tags',
                'products',
                'brands',
                'lastModified'
            ))
            ->header('Content-Type', 'application/xml');
    }
}