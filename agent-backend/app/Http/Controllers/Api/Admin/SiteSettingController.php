<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;

use App\Http\Requests\UpdateSiteSettingRequest;
use App\Http\Resources\SiteSettingResource;

use App\Models\SiteSetting;

use App\Services\CacheService;
use App\Support\HomepageSections;
use App\Support\ApiResponse;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SiteSettingController extends Controller
{
    /**
     * Singleton settings record.
     */
    protected function settings(): SiteSetting
    {
        return SiteSetting::firstOrCreate([
            'id' => 1,
        ]);
    }

    /**
     * GET /admin/settings
     */
    public function show(): JsonResponse
    {
        return ApiResponse::success(
            SiteSettingResource::make($this->settings()),
            'Site settings retrieved successfully.'
        );
    }

    /**
     * PUT /admin/settings
     */
    public function update(
        UpdateSiteSettingRequest $request
    ): JsonResponse {

        $settings = $this->settings();

        $settings->update($request->validated());

        CacheService::clearSiteSettings();

        return ApiResponse::success(
            SiteSettingResource::make($settings->refresh()),
            'Site settings updated successfully.'
        );
    }

    public function updateHomepage(Request $request): JsonResponse
    {
        $keys = array_keys(HomepageSections::DEFAULTS);
        $validated = $request->validate([
            'homepage_sections' => ['required', 'array'],
            'homepage_sections.*' => ['boolean'],
        ]);
        $sections = $validated['homepage_sections'];
        if (array_diff(array_keys($sections), $keys) || array_diff($keys, array_keys($sections))) {
            return ApiResponse::error('Every supported homepage section must be provided.', null, 422);
        }
        $settings = $this->settings();
        $settings->update(['homepage_sections' => $sections]);
        CacheService::clearSiteSettings();
        CacheService::clearHomepage();

        return ApiResponse::success(
            SiteSettingResource::make($settings->refresh()),
            'Homepage settings saved successfully.'
        );
    }

    /**
     * POST /admin/settings/assets
     */
    public function uploadAssets(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'favicon' => 'nullable|image|mimes:png,ico,webp|max:1024',
        ]);

        $settings = $this->settings();

        foreach (['logo', 'favicon'] as $field) {

            if (! $request->hasFile($field)) {
                continue;
            }

            if (
                $settings->{$field} &&
                Storage::disk('public')->exists($settings->{$field})
            ) {
                Storage::disk('public')->delete($settings->{$field});
            }

            $file = $request->file($field);

            $path = $file->storeAs(
                'uploads/settings',
                Str::uuid() . '.' . $file->extension(),
                'public'
            );

            $settings->{$field} = $path;
        }

        $settings->save();

        CacheService::clearSiteSettings();

        return ApiResponse::success(
            SiteSettingResource::make($settings->refresh()),
            'Site assets updated successfully.'
        );
    }
}
