<?php

namespace App\Services\AffiliateAgent;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

class MediaSelectionAgent
{
    public function select(array $scenes, string $productName = '', string $brandName = '', bool $reselect = false, string $categoryName = ''): array
    {
        $media = Media::where('folder', 'affiliate-agent')->whereIn('mime_type', ['video/mp4', 'image/png', 'image/jpeg', 'image/webp'])->orderBy('id')->get()
            ->filter(fn ($m) => in_array($m->disk, ['local', 'public'], true) && Storage::disk($m->disk)->exists($m->path));
        $used = [];
        foreach ($scenes as &$scene) {
            if (!empty($scene['media_id']) || ($scene['media_mode'] ?? 'auto') === 'manual' || !empty($scene['media_locked']) || in_array($scene['background_type'] ?? '', ['solid_light', 'solid_dark'], true) || (!$reselect && ($scene['background_type'] ?? '') === 'animated_abstract')) continue;
            $purpose = $scene['purpose'] ?? 'benefit';
            $style = $scene['visual_style'] ?? 'technology';
            $subject = mb_strtolower($productName.' '.$brandName.' '.$categoryName);
            $description = mb_strtolower(($scene['headline'] ?? '').' '.($scene['body'] ?? '').' '.($scene['text'] ?? ''));
            $stop = ['this', 'that', 'with', 'your', 'from', 'into', 'have', 'more', 'video', 'scene', 'show', 'using', 'about', 'make'];
            $words = fn ($value) => array_values(array_unique(array_filter(preg_split('/[^\pL\pN]+/u', $value), fn ($term) => mb_strlen($term) > 3 && !in_array($term, $stop, true))));
            $subjectTerms = $words($subject);
            $terms = $words($description);
            $context = $subject.' '.$description;
            $semanticTags = match (true) {
                preg_match('/host|server|cloud|website|network|datacenter|data center/i', $context) === 1 => ['hosting', 'server', 'cloud', 'website', 'network', 'datacenter'],
                preg_match('/nature|forest|plant|outdoor|ocean|landscape|lifestyle/i', $context) === 1 => ['nature', 'forest', 'plant', 'outdoor', 'ocean', 'landscape', 'lifestyle'],
                preg_match('/tech|computer|digital|coding|software|\bai\b/i', $context) === 1 => ['technology', 'computer', 'digital', 'device', 'coding', 'software', 'ai'],
                default => [],
            };
            $scored = $media->filter(fn ($m) => !isset($scene['media_type']) || ($scene['media_type'] === 'video' ? $m->mime_type === 'video/mp4' : $m->mime_type !== 'video/mp4'))->map(function ($m) use ($subjectTerms, $terms, $style, $purpose, $used, $semanticTags) {
                $label = mb_strtolower($m->name.' '.$m->file_name.' '.($m->alt_text ?? '').' '.($m->caption ?? ''));
                $score = 0;
                foreach ($subjectTerms as $term) if (preg_match('/\b'.preg_quote($term, '/').'\b/u', $label)) $score += 6;
                foreach ($terms as $term) if (preg_match('/\b'.preg_quote($term, '/').'\b/u', $label)) $score += 4;
                foreach ($semanticTags as $tag) if (preg_match('/\b'.preg_quote($tag, '/').'\b/u', $label)) $score += 3;
                foreach (match ($style) {
                    'nature' => ['nature','plant','forest','outdoor','ocean','landscape','travel','lifestyle'],
                    'technology' => ['tech','screen','digital','computer','ai','software'],
                    'product' => ['product','demo','interface','screen','screenshot','recording'],
                    'cinematic' => ['cinematic','film','lifestyle','outro'],
                    default => [$style],
                } as $tag) if (preg_match('/\b'.preg_quote($tag, '/').'\b/u', $label)) $score += 2;
                if ($purpose === 'demo' && preg_match('/demo|interface|screen|recording|product/i', $label)) $score += 3;
                if ($purpose === 'closing' && preg_match('/outro|closing|brand|product/i', $label)) $score += 2;
                // Prefer video only after matching the asset to the scene.
                if ($score < 2) return [$m, 0];
                if ($m->mime_type === 'video/mp4') $score += 4;
                if (in_array($m->id, $used, true)) $score -= 8;
                return [$m, $score];
            })->sort(fn ($a, $b) => ($b[1] <=> $a[1]) ?: ($a[0]->id <=> $b[0]->id))->values();
            if ($scored->isNotEmpty() && $scored[0][1] >= 2) {
                $scene['media_id'] = $scored[0][0]->id;
                $scene['background_type'] = 'media';
                $scene['media_mode'] = 'auto';
                $scene['media_origin'] = 'auto';
                $scene['media_type'] = $scored[0][0]->mime_type === 'video/mp4' ? 'video' : 'image';
                $used[] = $scene['media_id'];
            } else {
                $scene['background_type'] = 'animated_abstract';
                $scene['media_mode'] = 'auto';
                $scene['media_origin'] = 'fallback';
            }
        }
        unset($scene);
        return $scenes;
    }
}
