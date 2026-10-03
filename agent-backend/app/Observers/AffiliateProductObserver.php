<?php

namespace App\Observers;

use App\Models\AffiliateProduct;
use App\Services\CacheService;

class AffiliateProductObserver
{
    public function created(AffiliateProduct $product): void
    {
        CacheService::clearProductCaches($product->slug);
    }

    public function updated(AffiliateProduct $product): void
    {
        CacheService::clearProductCaches($product->slug);
    }

    public function deleted(AffiliateProduct $product): void
    {
        CacheService::clearProductCaches($product->slug);
    }

    public function restored(AffiliateProduct $product): void
    {
        CacheService::clearProductCaches($product->slug);
    }
}