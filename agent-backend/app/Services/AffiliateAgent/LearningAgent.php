<?php

namespace App\Services\AffiliateAgent;

use App\Models\Agent\Content;

class LearningAgent
{
    public function recentTopics(int $brandId): array
    {
        return Content::where('brand_id', $brandId)->latest()->limit(40)->pluck('title')->all();
    }
}
