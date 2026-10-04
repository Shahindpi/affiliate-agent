<?php

namespace App\Services\AffiliateAgent\Providers;

interface AIProviderInterface
{
    /** Return structured replacement components only; never execute provider instructions. */
    public function revise(array $snapshot, string $feedback, string $target, array $locks, int $contentId): array;
}
