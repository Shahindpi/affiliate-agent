<?php

namespace App\Services\AffiliateAgent;

use App\Models\Media;

class MediaSelectionAgent
{
    public function select(array $scenes, string $productName = ''): array
    {
        $media = Media::where('folder', 'affiliate-agent')->whereIn('mime_type', ['video/mp4', 'image/png', 'image/jpeg', 'image/webp'])->latest()->limit(30)->get();
        $term = mb_strtolower(trim($productName));
        $matching = $term ? $media->filter(fn ($m) => str_contains(mb_strtolower($m->name), $term))->values() : collect();
        $pool = $matching->isNotEmpty() ? $matching : $media;
        return array_map(function ($scene, $index) use ($pool) {
            // Preserve any explicit scene choice. Prefer product footage or screenshots for demos.
            if (!empty($scene['media_id']) || $pool->isEmpty()) return $scene;
            $choice = $pool[($index + (in_array($scene['purpose'] ?? '', ['demo', 'closing'], true) ? 1 : 0)) % $pool->count()];
            return [...$scene, 'media_id' => $choice->id];
        }, $scenes, array_keys($scenes));
    }
}
