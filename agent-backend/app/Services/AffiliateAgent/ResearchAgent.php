<?php

namespace App\Services\AffiliateAgent;

use App\Models\AffiliateProduct;

class ResearchAgent
{
    public function context(AffiliateProduct $product): array
    {
        $sources = app(BrandIntelligenceAgent::class)->knowledge($product->brand);
        if (!$sources) throw new \RuntimeException('Approve at least one official source document before automatic generation.');
        if (!$product->affiliate_url) throw new \RuntimeException('Set the product affiliate tracking URL before generation.');
        return ['brand' => $product->brand->name, 'product' => $product->name, 'affiliate_url' => $product->affiliate_url, 'sources' => $sources];
    }
}
