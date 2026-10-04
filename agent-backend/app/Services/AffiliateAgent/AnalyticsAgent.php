<?php

namespace App\Services\AffiliateAgent;

use App\Models\Agent\Publication;

class AnalyticsAgent
{
    public function summary(int $productId): array
    {
        return Publication::query()->join('agent_contents', 'agent_publications.content_id', '=', 'agent_contents.id')
            ->where('agent_contents.affiliate_product_id', $productId)
            ->selectRaw('agent_publications.platform, agent_publications.status, count(*) as total')
            ->groupBy('agent_publications.platform', 'agent_publications.status')->get()->toArray();
    }
}
