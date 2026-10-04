<?php

namespace App\Services\AffiliateAgent;

use App\Models\Agent\Content;
use App\Models\Agent\Campaign;
use App\Models\Agent\Setting;
use App\Models\AffiliateProduct;

class ContentPlannerAgent
{
    public function next(Setting $settings): array
    {
        $mix = $settings->mix;
        if (array_sum($mix) !== 100) throw new \RuntimeException('Content mix must total 100%.');
        $from = now($settings->timezone)->subDays($settings->horizon_days)->toDateString();
        $counts = Content::whereDate('created_at', '>=', $from)->selectRaw('content_type, count(*) as total')->groupBy('content_type')->pluck('total', 'content_type');
        $total = max(1, $counts->sum() + 1);
        $type = collect($mix)->sortKeys()->sortByDesc(fn ($percent, $key) => $percent * $total / 100 - ($counts[$key] ?? 0))->keys()->first();
        $products = AffiliateProduct::with('brand')->where('status', true)->whereHas('brand', fn ($q) => $q->where('status', true))
            ->when($settings->enabled_brand_ids, fn ($q) => $q->whereIn('brand_id', $settings->enabled_brand_ids))->get();
        $today = now($settings->timezone)->toDateString();
        $campaign = Campaign::with('product')->where('enabled', true)->when($settings->enabled_campaign_ids, fn ($q) => $q->whereIn('id', $settings->enabled_campaign_ids))->where(fn ($q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))->orderByDesc('priority')->get()
            ->first(fn ($c) => $products->contains(fn ($p) => $p->brand_id === $c->brand_id && (!$c->affiliate_product_id || $p->id === $c->affiliate_product_id))
                && Content::where('campaign_id', $c->id)->whereDate('created_at', '>=', now($settings->timezone)->startOfMonth()->toDateString())->count() < $c->monthly_target);
        $product = $campaign?->product ?: $products->where('brand_id', $campaign?->brand_id)->first();
        $product ??= $products->sortBy(fn ($p) => Content::where('affiliate_product_id', $p->id)->whereDate('created_at', '>=', $from)->count())->first();
        if (!$product) throw new \RuntimeException('No active affiliate product from an enabled brand is available.');
        return ['type' => $type, 'product' => $product, 'campaign' => $campaign, 'recent_topics' => app(LearningAgent::class)->recentTopics($product->brand_id), 'performance' => app(AnalyticsAgent::class)->summary($product->id)];
    }
}
