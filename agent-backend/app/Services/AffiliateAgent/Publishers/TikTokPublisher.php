<?php

namespace App\Services\AffiliateAgent\Publishers;

use App\Models\Agent\{ContentVersion,Publication,SocialAccount};
use Illuminate\Support\Facades\Http;

class TikTokPublisher implements SocialPublisherInterface
{
    use PublisherSupport;
    public function publish(ContentVersion $version, SocialAccount $account, Publication $publication): array
    {
        $bytes = $this->bytes($version); $size = strlen($bytes);
        if ($size > 64 * 1024 * 1024) return ['status' => 'MANUAL_ACTION_REQUIRED', 'metadata' => ['reason' => 'Video exceeds single-chunk upload limit; use manual export.']];
        $token = $this->token($account); $m = $this->metadata($version, 'tiktok');
        $direct = $account->api_review_status === 'APPROVED' && in_array('video.publish', $account->scopes ?? [], true);
        if ($direct) {
            $creator = Http::withToken($token)->post('https://open.tiktokapis.com/v2/post/publish/creator_info/query/', (object) [])->throw()->json();
            $levels = $creator['data']['privacy_level_options'] ?? [];
            $privacy = in_array('PUBLIC_TO_EVERYONE', $levels, true) ? 'PUBLIC_TO_EVERYONE' : ($levels[0] ?? null);
            if (!$privacy) return ['status' => 'MANUAL_ACTION_REQUIRED', 'metadata' => ['reason' => 'Creator has no eligible privacy level.']];
            $payload = ['post_info' => ['title' => $this->caption($m), 'privacy_level' => $privacy, 'disable_duet' => false, 'disable_comment' => false, 'disable_stitch' => false, 'is_aigc' => true], 'source_info' => ['source' => 'FILE_UPLOAD', 'video_size' => $size, 'chunk_size' => $size, 'total_chunk_count' => 1]];
            $endpoint = 'https://open.tiktokapis.com/v2/post/publish/video/init/';
        } else {
            $payload = ['source_info' => ['source' => 'FILE_UPLOAD', 'video_size' => $size, 'chunk_size' => $size, 'total_chunk_count' => 1]];
            $endpoint = 'https://open.tiktokapis.com/v2/post/publish/inbox/video/init/';
        }
        $init = Http::withToken($token)->post($endpoint, $payload)->throw()->json();
        if (($init['error']['code'] ?? '') !== 'ok') throw new \RuntimeException('TikTok init: '.($init['error']['code'] ?? 'unknown error'));
        $url = $init['data']['upload_url'] ?? '';
        if (!str_starts_with($url, 'https://open-upload.tiktokapis.com/')) throw new \RuntimeException('TikTok returned an untrusted upload URL.');
        Http::withHeaders(['Content-Range' => 'bytes 0-'.($size - 1).'/'.$size, 'Content-Length' => $size])->withBody($bytes, 'video/mp4')->put($url)->throw();
        return ['status' => $direct ? 'PROCESSING' : 'MANUAL_ACTION_REQUIRED', 'id' => $init['data']['publish_id'] ?? null, 'metadata' => ['upload' => $direct ? 'direct_post_processing' : 'draft_uploaded_complete_in_tiktok', 'ai_generated' => true]];
    }

    public function poll(SocialAccount $account, Publication $publication): array
    {
        $response = Http::withToken($this->token($account))->post('https://open.tiktokapis.com/v2/post/publish/status/fetch/', ['publish_id' => $publication->external_id])->throw()->json();
        if (($response['error']['code'] ?? '') !== 'ok') throw new \RuntimeException('TikTok status: '.($response['error']['code'] ?? 'unknown'));
        $status = $response['data']['status'] ?? 'UNKNOWN';
        if ($status === 'PUBLISH_COMPLETE') return ['status' => 'PUBLISHED', 'id' => $publication->external_id, 'metadata' => ['status' => $status, 'public_post_ids' => $response['data']['publicaly_available_post_id'] ?? []]];
        if ($status === 'FAILED') return ['status' => 'MANUAL_ACTION_REQUIRED', 'metadata' => ['reason' => $response['data']['fail_reason'] ?? 'TikTok rejected the post']];
        return ['status' => 'PROCESSING', 'id' => $publication->external_id, 'metadata' => ['status' => $status]];
    }
}
