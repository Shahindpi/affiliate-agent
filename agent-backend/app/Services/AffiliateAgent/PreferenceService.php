<?php

namespace App\Services\AffiliateAgent;

use App\Models\Agent\Content;
use App\Models\Agent\Preference;

class PreferenceService
{
    public function applicable(Content $content): array
    {
        return Preference::where('enabled', true)->where(function ($q) use ($content) {
            $q->where('scope', 'global');
            if ($content->brand_id) $q->orWhere(fn ($b) => $b->where('scope', 'brand')->where('brand_id', $content->brand_id));
            if ($content->affiliate_product_id) $q->orWhere(fn ($p) => $p->where('scope', 'product')->where('affiliate_product_id', $content->affiliate_product_id));
        })->orderByRaw("case scope when 'global' then 0 when 'brand' then 1 else 2 end")->orderBy('id')->get(['component', 'instruction'])->toArray();
    }
}
