<?php

namespace App\Observers;

use App\Models\Tag;
use App\Services\CacheService;

class TagObserver
{
    public function created(Tag $tag): void
    {
        CacheService::clearTagCaches($tag->slug);
    }

    public function updated(Tag $tag): void
    {
        CacheService::clearTagCaches($tag->slug);
    }

    public function deleted(Tag $tag): void
    {
        CacheService::clearTagCaches($tag->slug);
    }

    public function restored(Tag $tag): void
    {
        CacheService::clearTagCaches($tag->slug);
    }
}