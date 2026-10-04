<?php

namespace App\Services\AffiliateAgent;

use App\Models\Media;

class MediaSelectionAgent
{
    public function select(array $scenes): array
    {
        $media = Media::where('folder', 'affiliate-agent')->whereIn('mime_type', ['video/mp4', 'image/png', 'image/jpeg', 'image/webp'])->latest()->first();
        if (!$media) throw new \RuntimeException('Upload official product screenshots or screen recordings before automatic generation.');
        return array_map(fn ($scene) => [...$scene, 'media_id' => $media->id], $scenes);
    }
}
