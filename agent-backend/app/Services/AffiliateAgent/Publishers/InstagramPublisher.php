<?php

namespace App\Services\AffiliateAgent\Publishers;

use App\Models\Agent\{ContentVersion,Publication,SocialAccount};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class InstagramPublisher implements SocialPublisherInterface
{
    use PublisherSupport;
    public function publish(ContentVersion $version, SocialAccount $account, Publication $publication): array
    {
        if (!str_starts_with(config('app.url'), 'https://')) return ['status' => 'MANUAL_ACTION_REQUIRED', 'metadata' => ['reason' => 'Instagram requires a public HTTPS video URL. Set APP_URL.']];
        $url = URL::temporarySignedRoute('agent.asset', now()->addHours(2), ['version' => $version->id, 'kind' => 'video']);
        $token = $this->token($account); $graph = 'https://graph.facebook.com/'.config('affiliate_agent.meta_graph_version');
        $m = $this->metadata($version, 'instagram');
        $container = Http::asForm()->post($graph.'/'.$account->external_id.'/media', ['media_type' => 'REELS', 'video_url' => $url, 'caption' => $this->caption($m), 'access_token' => $token])->throw()->json();
        $id = $container['id'] ?? throw new \RuntimeException('Instagram returned no media container ID.');
        return ['status' => 'PROCESSING', 'id' => $id, 'metadata' => ['container_id' => $id, 'step' => 'await_processing']];
    }

    public function finish(SocialAccount $account, Publication $publication): array
    {
        $graph = 'https://graph.facebook.com/'.config('affiliate_agent.meta_graph_version'); $token = $this->token($account);
        $container = $publication->response_metadata['container_id'];
        $status = Http::get($graph.'/'.$container, ['fields' => 'status_code', 'access_token' => $token])->throw()->json('status_code');
        if ($status === 'ERROR' || $status === 'EXPIRED') return ['status' => 'MANUAL_ACTION_REQUIRED', 'metadata' => ['reason' => 'Instagram media container '.$status]];
        if ($status !== 'FINISHED') return ['status' => 'PROCESSING', 'metadata' => ['container_id' => $container, 'step' => 'await_processing', 'processing' => $status]];
        $posted = Http::asForm()->post($graph.'/'.$account->external_id.'/media_publish', ['creation_id' => $container, 'access_token' => $token])->throw()->json();
        $id = $posted['id'] ?? throw new \RuntimeException('Instagram did not return a media ID; inspect before retrying.');
        return ['status' => 'PUBLISHED', 'id' => $id, 'metadata' => ['container_id' => $container]];
    }
}
