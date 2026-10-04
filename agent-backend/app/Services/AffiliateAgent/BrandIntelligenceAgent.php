<?php

namespace App\Services\AffiliateAgent;

use App\Models\Brand;
use App\Models\Agent\SourceDocument;

class BrandIntelligenceAgent
{
    public function knowledge(Brand $brand): array
    {
        return SourceDocument::query()->where('status', 'APPROVED')->whereHas('source', fn ($q) => $q->where('brand_id', $brand->id)->where('enabled', true))->latest('synced_at')->limit(8)->get()->map(fn ($d) => ['source_url' => $d->source_url, 'synced_at' => $d->synced_at?->toIso8601String(), 'facts' => mb_substr($d->body, 0, 6000)])->all();
    }
}
