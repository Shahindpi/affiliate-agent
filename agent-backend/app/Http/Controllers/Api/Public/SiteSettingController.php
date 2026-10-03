<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

use App\Http\Resources\PublicSiteSettingResource;
use App\Models\SiteSetting;
use App\Services\CacheService;
use App\Support\ApiResponse;

class SiteSettingController extends Controller
{
    /**
     * Public website settings.
     */
    public function show(): JsonResponse
    {
        $settings = Cache::remember(

            CacheService::siteSettingsKey(),

            now()->addHours(24),

            function () {

                return SiteSetting::firstOrCreate([
                    'id' => 1,
                ]);

            }

        );

        return ApiResponse::success(

            PublicSiteSettingResource::make($settings),

            'Public site settings retrieved successfully.'

        );
    }
}