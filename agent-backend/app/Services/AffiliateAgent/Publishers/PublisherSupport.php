<?php

namespace App\Services\AffiliateAgent\Publishers;

use App\Models\Agent\ContentVersion;
use App\Services\AffiliateAgent\AssetStore;

trait PublisherSupport
{
    private function bytes(ContentVersion $version): string { return file_get_contents(app(AssetStore::class)->absolute($version->artifacts['video'])); }
    private function metadata(ContentVersion $version, string $platform): array { return $version->snapshot['metadata'][$platform]; }
    private function caption(array $m): string { return trim($m['caption'].' '.$m['disclosure']); }
    private function token(\App\Models\Agent\SocialAccount $account): string { return app(\App\Services\AffiliateAgent\SocialOAuthService::class)->token($account); }
}
