<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroBanner;
use App\Services\CacheService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeroBannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = HeroBanner::query()->orderBy('sort_order')->orderBy('id')->paginate(
            $request->input('per_page') === 'all' ? max(1, HeroBanner::count()) : min(max($request->integer('per_page', 20), 1), 100)
        );
        return response()->json(['success' => true, 'data' => $page->items(), 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $banner = HeroBanner::create($this->validated($request));
        CacheService::clearPublicCaches();
        return ApiResponse::success($banner, 'Hero banner created successfully.', 201);
    }

    public function show(HeroBanner $heroBanner): JsonResponse
    {
        return ApiResponse::success($heroBanner);
    }

    public function update(Request $request, HeroBanner $heroBanner): JsonResponse
    {
        $heroBanner->update($this->validated($request));
        CacheService::clearPublicCaches();
        return ApiResponse::success($heroBanner->refresh(), 'Hero banner updated successfully.');
    }

    public function destroy(HeroBanner $heroBanner): JsonResponse
    {
        $heroBanner->delete();
        CacheService::clearPublicCaches();
        return ApiResponse::success(null, 'Hero banner deleted successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'heading' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'background_image' => ['nullable', 'string', 'max:2048'],
            'cta_text' => ['nullable', 'required_with:cta_url', 'string', 'max:100'],
            'cta_url' => ['nullable', 'required_with:cta_text', 'string', 'max:2048', function ($attribute, $value, $fail) {
                if (! str_starts_with($value, '/') && ! (preg_match('/^https?:\/\//i', $value) && filter_var($value, FILTER_VALIDATE_URL))) $fail('The CTA must be a local path or an absolute URL.');
                if (str_starts_with($value, '//')) $fail('Protocol-relative URLs are not allowed.');
            }],
            'enabled' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
    }
}
