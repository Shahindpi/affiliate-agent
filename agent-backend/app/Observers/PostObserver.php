<?php

namespace App\Observers;

use App\Models\Post;
use App\Services\CacheService;

class PostObserver
{
    public function created(Post $post): void
    {
        CacheService::clearPostCaches($post->slug);
    }

    public function updated(Post $post): void
    {
        CacheService::clearPostCaches($post->slug);
    }

    public function deleted(Post $post): void
    {
        CacheService::clearPostCaches($post->slug);
    }

    public function restored(Post $post): void
    {
        CacheService::clearPostCaches($post->slug);
    }
}