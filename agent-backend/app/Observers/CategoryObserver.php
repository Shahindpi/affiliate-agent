<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\CacheService;

class CategoryObserver
{
    public function created(Category $category): void
    {
        CacheService::clearCategoryCaches($category->slug);
    }

    public function updated(Category $category): void
    {
        CacheService::clearCategoryCaches($category->slug);
    }

    public function deleted(Category $category): void
    {
        CacheService::clearCategoryCaches($category->slug);
    }

    public function restored(Category $category): void
    {
        CacheService::clearCategoryCaches($category->slug);
    }
}