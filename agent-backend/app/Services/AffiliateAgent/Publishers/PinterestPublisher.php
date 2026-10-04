<?php

namespace App\Services\AffiliateAgent\Publishers;

use App\Models\Agent\{ContentVersion,Publication,SocialAccount};
use Illuminate\Support\Facades\Http;

class PinterestPublisher implements SocialPublisherInterface
{
    use PublisherSupport;
    public function publish(ContentVersion $version, SocialAccount $account, Publication $publication): array
    {
        $destination = app(\App\Services\AffiliateAgent\PinterestDestinationService::class)->forContent($account, $version->content);
        $board = $destination?->external_board_id;
        $section = $destination?->external_section_id;
        $cover = $account->metadata['cover_image_url'] ?? null;
        if (!$board || !filter_var($cover, FILTER_VALIDATE_URL) || parse_url($cover, PHP_URL_SCHEME) !== 'https') return ['status' => 'MANUAL_ACTION_REQUIRED', 'metadata' => ['reason' => 'Select a Pinterest board and public HTTPS cover image URL.']];
        $token = $this->token($account); $m = $this->metadata($version, 'pinterest');
        $mediaId = $publication->response_metadata['media_id'] ?? null;
        if (!$mediaId) {
            $upload = Http::withToken($token)->post('https://api.pinterest.com/v5/media', ['media_type' => 'video'])->throw()->json();
            $mediaId = $upload['media_id'] ?? throw new \RuntimeException('Pinterest did not return a media ID.');
            $publication->update(['response_metadata' => ['media_id' => $mediaId]]);
            $url = $upload['upload_url'] ?? '';
            if (!str_starts_with($url, 'https://') || !str_ends_with(parse_url($url, PHP_URL_HOST) ?: '', '.amazonaws.com')) throw new \RuntimeException('Pinterest returned an untrusted media upload URL.');
            Http::timeout(120)->asMultipart()->attach('file', $this->bytes($version), 'master.mp4', ['Content-Type' => 'video/mp4'])->post($url, $upload['upload_parameters'] ?? [])->throw();
        }
        $status = Http::withToken($token)->get('https://api.pinterest.com/v5/media/'.$mediaId)->throw()->json();
        if (($status['status'] ?? '') !== 'succeeded') return ['status' => 'RETRY_PENDING', 'metadata' => ['media_id' => $mediaId, 'processing' => $status['status'] ?? 'unknown']];
        $pin = Http::withToken($token)->post('https://api.pinterest.com/v5/pins', ['board_id' => $board, ...($section ? ['board_section_id' => $section] : []), 'title' => $m['title'], 'description' => $this->caption($m), 'link' => $m['affiliate_url'], 'media_source' => ['source_type' => 'video_id', 'cover_image_url' => $cover, 'media_id' => $mediaId]])->throw()->json();
        $id = $pin['id'] ?? throw new \RuntimeException('Pinterest did not return a Pin ID; inspect provider before retrying.');
        return ['status' => 'PUBLISHED', 'id' => $id, 'url' => 'https://www.pinterest.com/pin/'.$id.'/', 'metadata' => ['media_id' => $mediaId]];
    }
}
