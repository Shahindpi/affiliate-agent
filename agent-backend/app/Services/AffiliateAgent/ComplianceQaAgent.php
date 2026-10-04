<?php

namespace App\Services\AffiliateAgent;

use Symfony\Component\Process\Process;

class ComplianceQaAgent
{
    public function check(array $snapshot, array $artifacts): array
    {
        app(ContentSchema::class)->validate($snapshot);
        $p = new Process([config('affiliate_agent.ffprobe'), '-v', 'error', '-show_streams', '-show_format', '-of', 'json', app(AssetStore::class)->absolute($artifacts['video'])]);
        $p->setTimeout(30)->mustRun();
        $data = json_decode($p->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $video = collect($data['streams'])->firstWhere('codec_type', 'video');
        $audio = collect($data['streams'])->firstWhere('codec_type', 'audio');
        $duration = (float) $data['format']['duration'];
        $checks = [
            'dimensions' => ($video['width'] ?? 0) === 1080 && ($video['height'] ?? 0) === 1920,
            'video_codec' => ($video['codec_name'] ?? '') === 'h264',
            'audio_codec' => ($audio['codec_name'] ?? '') === 'aac',
            'duration' => $duration >= 14.9 && $duration <= 45.5,
            'voice_integrity' => app(AssetStore::class)->verify($artifacts['voice']),
            'disclosures' => trim($snapshot['disclosure']) !== '' && collect($snapshot['metadata'])->every(fn ($m) => trim($m['disclosure']) !== ''),
            'affiliate_urls' => collect($snapshot['metadata'])->every(fn ($m) => !empty($m['affiliate_url'])),
        ];
        foreach ($artifacts['scenes'] ?? [] as $id => $asset) $checks['scene_'.$id] = app(AssetStore::class)->verify($asset);
        return ['passed' => !in_array(false, $checks, true), 'checks' => $checks, 'duration' => $duration, 'review_required' => 'An admin must verify factual claims, media rights, pronunciation, captions and affiliate disclosure before final approval.'];
    }
}
