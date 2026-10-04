<?php

namespace App\Services\AffiliateAgent;

class PlatformMetadataAgent
{
    public function create(string $title, array $draft, string $link): array
    {
        $metadata = [];
        foreach (ContentSchema::PLATFORMS as $platform) $metadata[$platform] = ['title' => $title, 'caption' => $draft['captions'].' '.$draft['disclosure'], 'description' => $draft['script'], 'hashtags' => ['#'.preg_replace('/\W+/', '', $platform)], 'affiliate_url' => $link, 'cta' => $draft['cta'], 'disclosure' => $draft['disclosure']];
        return $metadata;
    }
}
