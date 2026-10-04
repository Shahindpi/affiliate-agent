<?php

namespace App\Services\AffiliateAgent\Publishers;

use App\Models\Agent\{ContentVersion,Publication,SocialAccount};
use Illuminate\Support\Facades\Http;

class YouTubePublisher implements SocialPublisherInterface
{
    use PublisherSupport;
    public function publish(ContentVersion $version, SocialAccount $account, Publication $publication): array
    {
        $bytes = $this->bytes($version); $m = $this->metadata($version, 'youtube');
        $privacy = ($account->api_review_status === 'APPROVED') ? 'public' : 'private';
        $init = Http::withToken($this->token($account))->withHeaders(['X-Upload-Content-Type' => 'video/mp4', 'X-Upload-Content-Length' => strlen($bytes)])->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', [
            'snippet' => ['title' => $m['title'], 'description' => $m['description']."\n".$this->caption($m)."\n".$m['affiliate_url']."\n#Shorts", 'tags' => $m['hashtags']],
            'status' => ['privacyStatus' => $privacy, 'selfDeclaredMadeForKids' => false],
        ])->throw();
        $location = $init->header('Location');
        if (!$location || !str_starts_with($location, 'https://www.googleapis.com/')) throw new \RuntimeException('YouTube did not provide a trusted upload URL.');
        $upload = Http::withToken($this->token($account))->withHeaders(['Content-Type' => 'video/mp4', 'Content-Length' => strlen($bytes)])->withBody($bytes, 'video/mp4')->put($location)->throw()->json();
        $id = $upload['id'] ?? throw new \RuntimeException('YouTube upload response omitted video ID; inspect provider before retrying.');
        return ['status' => $privacy === 'public' ? 'PUBLISHED' : 'API_REVIEW_REQUIRED', 'id' => $id, 'url' => 'https://www.youtube.com/watch?v='.$id, 'metadata' => ['privacy' => $privacy, 'processing_status' => $upload['status']['uploadStatus'] ?? 'uploaded']];
    }
}
