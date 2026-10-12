<?php

namespace App\Services\AffiliateAgent;

use Illuminate\Validation\ValidationException;

class SceneMediaRevision
{
    private const FIELDS = ['media_id', 'media_mode', 'media_locked', 'media_type', 'media_fit', 'media_focus', 'overlay_mode', 'media_origin', 'background_type'];
    private const ASSET_FIELDS = ['media_id', 'media_mode', 'media_type', 'background_type'];

    public function hasIntent(string $feedback): bool
    {
        return preg_match('/(?:use|replace|change|keep|lock|do not change|don.t change).*\b(?:background|media|video|image|footage)\b/i', $feedback) === 1;
    }

    public function apply(array $before, array $after, string $feedback, bool $adminPatch): array
    {
        $old = array_column($before, null, 'id');
        $allProtected = preg_match('/(?:do not|don.t) change (?:any |the )?(?:background|media)/i', $feedback) === 1;
        $keep = preg_match('/\bkeep\b.*\b(?:current|existing|background|video|image|media)\b/i', $feedback) === 1;
        $replace = preg_match('/\b(?:use|replace|change)\b.*\b(?:background|video|image|footage|media)\b/i', $feedback) === 1;
        $index = null;
        if (preg_match('/\bscene\s*(\d+)\b/i', $feedback, $m)) $index = (int) $m[1] - 1;
        elseif (preg_match('/\bhook\b/i', $feedback)) $index = 0;
        elseif (preg_match('/\bclos(?:ing|e)\b/i', $feedback)) $index = count($after) - 1;
        if (($keep || $replace) && !$adminPatch && !$allProtected && ($index === null || !isset($after[$index]))) throw ValidationException::withMessages(['feedback' => 'Specify a scene number, hook or closing scene for the media request.']);
        if ($replace && !$adminPatch && preg_match('/\buploaded\b/i', $feedback)) throw ValidationException::withMessages(['feedback' => 'Select the uploaded asset in the scene background controls first.']);

        foreach ($after as $i => &$scene) {
            $previous = $old[$scene['id']] ?? null;
            if (!$previous) continue;
            $locked = !empty($previous['media_locked']);
            $explicit = !$adminPatch && $replace && $i === $index && !$allProtected;
            if ($locked) {
                foreach (self::ASSET_FIELDS as $field) {
                    $beforeValue = $previous[$field] ?? ($field === 'media_mode' ? (empty($previous['media_id']) ? 'auto' : 'manual') : null);
                    $afterValue = $scene[$field] ?? ($field === 'media_mode' ? (empty($scene['media_id']) ? 'auto' : 'manual') : null);
                    if ($beforeValue !== $afterValue && ($adminPatch || $explicit)) throw ValidationException::withMessages(['media_locked' => "Unlock the background in scene {$scene['id']} in a separate revision before replacing it."]);
                }
            }
            if (!$adminPatch && ($locked || $allProtected || $keep && $i === $index || ($previous['media_mode'] ?? (empty($previous['media_id']) ? 'auto' : 'manual')) === 'manual' && !$explicit)) {
                foreach (self::FIELDS as $field) {
                    if (array_key_exists($field, $previous)) $scene[$field] = $previous[$field];
                    else unset($scene[$field]);
                }
            }
            if ($allProtected && !$adminPatch) $scene['media_locked'] = true;
            if ($keep && !$adminPatch && $i === $index) $scene['media_locked'] = true;
            if ($explicit) {
                if ($locked) throw ValidationException::withMessages(['media_locked' => "Unlock the background in scene {$scene['id']} first."]);
                $scene['media_mode'] = 'auto';
                $scene['media_id'] = null;
                $scene['background_type'] = 'media';
                $scene['media_origin'] = 'auto';
                $scene['media_locked'] = false;
                if (preg_match('/\b(nature|outdoor|forest|lifestyle|technology|computer|digital|cinematic|product)\b/i', $feedback, $m)) {
                    $scene['visual_style'] = match (strtolower($m[1])) {
                        'nature', 'outdoor', 'forest', 'lifestyle' => 'nature',
                        'technology', 'computer', 'digital' => 'technology',
                        'cinematic' => 'cinematic', default => 'product',
                    };
                }
                if (preg_match('/\bvideo|footage\b/i', $feedback)) $scene['media_type'] = 'video';
                elseif (preg_match('/\bimage|screenshot\b/i', $feedback)) $scene['media_type'] = 'image';
                else unset($scene['media_type']);
            }
        }
        unset($scene);
        return $after;
    }
}
