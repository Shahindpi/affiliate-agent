<?php

namespace App\Services\AffiliateAgent\Publishers;

use App\Models\Agent\ContentVersion;
use App\Models\Agent\Publication;
use App\Models\Agent\SocialAccount;

interface SocialPublisherInterface
{
    /** @return array{status:string, id?:string, url?:string, metadata?:array} */
    public function publish(ContentVersion $version, SocialAccount $account, Publication $publication): array;
}
