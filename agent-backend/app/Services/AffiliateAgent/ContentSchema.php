<?php

namespace App\Services\AffiliateAgent;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContentSchema
{
    public const PLATFORMS = ['pinterest', 'instagram', 'tiktok', 'facebook', 'youtube'];
    public const COMPONENTS = ['script', 'scenes', 'captions', 'cta', 'disclosure', 'metadata', 'voice', 'video'];

    public function validate(array $snapshot): array
    {
        // Initial drafts can precede platform metadata generation. Keep a complete
        // canonical shape so immutable versions and revisions use one schema.
        $snapshot['metadata'] ??= [];
        foreach (self::PLATFORMS as $platform) {
            $given = $snapshot['metadata'][$platform] ?? [];
            if (!is_array($given)) continue;
            $snapshot['metadata'][$platform] = array_replace([
                'title' => mb_substr($snapshot['script'] ?? 'Draft video', 0, 120),
                'caption' => $snapshot['captions'] ?? ($snapshot['script'] ?? 'Draft video'),
                'description' => $snapshot['script'] ?? 'Draft video',
                'hashtags' => [], 'affiliate_url' => '',
                'cta' => $snapshot['cta'] ?? '', 'disclosure' => $snapshot['disclosure'] ?? '',
            ], $given);
            $snapshot['metadata'][$platform]['hashtags'] ??= [];
        }
        $rules = [
            'script' => 'required|string|max:6000',
            'scenes' => 'required|array|min:1|max:12',
            'scenes.*' => 'required|array:id,text,duration,media_id',
            'scenes.*.id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,50}$/', 'distinct'],
            'scenes.*.text' => 'required|string|max:500',
            'scenes.*.duration' => 'required|numeric|min:1|max:45',
            'scenes.*.media_id' => 'present|nullable|integer|exists:media,id,deleted_at,NULL',
            'captions' => 'required|string|max:6000',
            'cta' => 'required|string|max:500',
            'disclosure' => 'required|string|max:500',
            'voice' => 'required|array:voice_id',
            'voice.voice_id' => ['present', 'nullable', 'string', 'regex:/^[a-zA-Z0-9_-]{1,100}$/'],
            'metadata' => 'required|array:'.implode(',', self::PLATFORMS),
        ];
        foreach (self::PLATFORMS as $platform) {
            $rules["metadata.$platform"] = 'required|array:title,caption,description,hashtags,affiliate_url,cta,disclosure';
            foreach (['title', 'caption', 'description', 'cta', 'disclosure'] as $field) {
                $rules["metadata.$platform.$field"] = 'required|string|max:6000';
            }
            $rules["metadata.$platform.affiliate_url"] = 'present|nullable|url:http,https|max:2000';
            $rules["metadata.$platform.hashtags"] = 'present|array|max:30';
            $rules["metadata.$platform.hashtags.*"] = 'string|max:100';
        }
        $unknown = array_diff(array_keys($snapshot), array_diff(self::COMPONENTS, ['video']));
        if ($unknown) throw ValidationException::withMessages(['snapshot' => 'Unknown components: '.implode(', ', $unknown)]);
        $validated = Validator::make($snapshot, $rules)->validate();
        $duration = array_sum(array_column($validated['scenes'], 'duration'));
        if ($duration < 15 || $duration > 45) throw ValidationException::withMessages(['scenes' => 'Total duration must be 15–45 seconds.']);
        return $validated;
    }

    public function validateTarget(string $target, array $snapshot): void
    {
        if ($target === 'all' || in_array($target, self::COMPONENTS, true)) return;
        if (preg_match('/^scenes\.([a-zA-Z0-9_-]+)$/', $target, $m) && in_array($m[1], array_column($snapshot['scenes'], 'id'), true)) return;
        if (preg_match('/^metadata\.('.implode('|', self::PLATFORMS).')$/', $target)) return;
        throw ValidationException::withMessages(['target' => 'Unknown component or scene.']);
    }

    public function canonical(array $value): string
    {
        $sort = function (array $data) use (&$sort): array {
            if (!array_is_list($data)) ksort($data);
            foreach ($data as &$v) if (is_array($v)) $v = $sort($v);
            return $data;
        };
        return json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function hash(array $snapshot, array $artifacts): string
    {
        return hash('sha256', $this->canonical(['snapshot' => $snapshot, 'artifacts' => $artifacts]));
    }
}
