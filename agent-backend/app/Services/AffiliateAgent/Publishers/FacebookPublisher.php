<?php

namespace App\Services\AffiliateAgent\Publishers;

use App\Models\Agent\{ContentVersion,Publication,SocialAccount};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class FacebookPublisher implements SocialPublisherInterface
{
    use PublisherSupport;
    public function publish(ContentVersion $version, SocialAccount $account, Publication $publication): array
    {
        if (!str_starts_with(config('app.url'), 'https://')) return ['status' => 'MANUAL_ACTION_REQUIRED', 'metadata' => ['reason' => 'Facebook Reel upload requires a public HTTPS video URL. Set APP_URL.']];
        $token = $this->token($account); $graph = 'https://graph.facebook.com/'.config('affiliate_agent.meta_graph_version');
        $start = Http::post($graph.'/'.$account->external_id.'/video_reels', ['upload_phase' => 'start', 'access_token' => $token])->throw()->json();
        $id = $start['video_id'] ?? throw new \RuntimeException('Facebook returned no Reel video ID.');
        $videoUrl = URL::temporarySignedRoute('agent.asset', now()->addHours(2), ['version' => $version->id, 'kind' => 'video']);
        Http::withHeaders(['Authorization' => 'OAuth '.$token, 'file_url' => $videoUrl])->post('https://rupload.facebook.com/video-upload/'.config('affiliate_agent.meta_graph_version').'/'.$id)->throw();
        $m = $this->metadata($version, 'facebook');
        $finish = Http::post($graph.'/'.$account->external_id.'/video_reels', ['upload_phase' => 'finish', 'video_id' => $id, 'video_state' => 'PUBLISHED', 'description' => $this->caption($m).' '.$m['affiliate_url'], 'access_token' => $token])->throw()->json();
        return ['status' => 'PROCESSING', 'id' => $id, 'url' => 'https://www.facebook.com/reel/'.$id, 'metadata' => ['finish_response' => ['success' => $finish['success'] ?? null], 'step' => 'processing']];
    }

    public function poll(SocialAccount $account, Publication $publication): array
    {
        $status = Http::withToken($this->token($account))->get('https://graph.facebook.com/'.config('affiliate_agent.meta_graph_version').'/'.$publication->external_id, ['fields' => 'status'])->throw()->json('status');
        $video = $status['video_status'] ?? 'unknown';
        if ($video === 'ready') return ['status' => 'PUBLISHED', 'id' => $publication->external_id, 'url' => $publication->platform_url, 'metadata' => ['video_status' => $video]];
        if ($video === 'error') return ['status' => 'MANUAL_ACTION_REQUIRED', 'metadata' => ['video_status' => $video]];
        return ['status' => 'PROCESSING', 'id' => $publication->external_id, 'metadata' => ['video_status' => $video]];
    }
}
