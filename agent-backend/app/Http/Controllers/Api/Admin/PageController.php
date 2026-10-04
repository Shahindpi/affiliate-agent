<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\PageContentSanitizer;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function index() { return ApiResponse::success(Page::with('seoMeta')->latest()->paginate(30)); }
    public function show(Page $page) { return ApiResponse::success($page->load('seoMeta')); }

    public function store(Request $r, PageContentSanitizer $sanitizer)
    {
        $data = $this->validatePage($r);
        $seo = $data['seo'] ?? []; unset($data['seo']);
        $data['content'] = $sanitizer->clean($data['content']); $data['user_id'] = $r->user()->id;
        $page = Page::create($data);
        $page->seoMeta()->create($this->seo($seo));
        return ApiResponse::success($page->load('seoMeta'), 'Page created.', 201);
    }

    public function update(Request $r, Page $page, PageContentSanitizer $sanitizer)
    {
        $data = $this->validatePage($r, $page);
        $seo = $data['seo'] ?? []; unset($data['seo']);
        $data['content'] = $sanitizer->clean($data['content']);
        $page->update($data);
        $page->seoMeta()->updateOrCreate([], $this->seo($seo));
        return ApiResponse::success($page->fresh('seoMeta'), 'Page updated. Public content is fetched on the next request.');
    }

    private function validatePage(Request $r, ?Page $page = null): array
    {
        $data = $r->validate([
            'title' => 'required|string|max:255', 'slug' => ['required','string','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/','max:280', Rule::unique('pages', 'slug')->ignore($page?->id)],
            'excerpt' => 'nullable|string|max:1000', 'content' => 'required|string|max:100000', 'status' => 'required|boolean',
            'seo' => 'nullable|array:meta_title,meta_description', 'seo.meta_title' => 'nullable|string|max:255', 'seo.meta_description' => 'nullable|string|max:500',
        ]);
        $reserved = ['admin','api','auth','posts','products','categories','brands','tags','contact','search','sitemap.xml','robots.txt','privacy-policy','terms','affiliate-disclosure'];
        if (in_array($data['slug'], $reserved, true) && $data['slug'] !== ($page?->slug ?? null) && $data['slug'] !== ($page?->legal_key ?? null)) abort(422, 'This slug is reserved.');
        return $data;
    }

    private function seo(array $data): array { return ['meta_title' => $data['meta_title'] ?? null, 'meta_description' => $data['meta_description'] ?? null]; }
}
