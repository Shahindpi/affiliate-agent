<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\ApiResponse;

class PageController extends Controller
{
    public function index()
    {
        return ApiResponse::success(Page::where('status', true)->whereNotNull('legal_key')->orderBy('id')->get(['title','slug','legal_key','updated_at']));
    }

    public function show(string $slug, \App\Services\PageContentSanitizer $sanitizer)
    {
        $page = Page::with('seoMeta')->where('status', true)->where(fn ($q) => $q->where('slug', $slug)->orWhere('legal_key', $slug))->firstOrFail();
        return ApiResponse::success(['title' => $page->title, 'slug' => $page->slug, 'legal_key' => $page->legal_key, 'excerpt' => $page->excerpt, 'content' => $sanitizer->clean($page->content), 'updated_at' => $page->updated_at, 'seo' => ['meta_title' => $page->seoMeta?->meta_title, 'meta_description' => $page->seoMeta?->meta_description]]);
    }
}
