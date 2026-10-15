<?php

namespace App\Services\AffiliateAgent;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\Media;

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
            'scenes.*' => 'required|array:id,text,duration,media_id,purpose,headline,body,visual_style,background_type,animation,layout,template,media_mode,media_locked,media_type,media_fit,media_focus,overlay_mode,media_origin,headline_font_size,body_font_size,headline_color,body_color,text_font_weight,text_stroke,text_stroke_color,text_shadow,cta_text_color,cta_background_color,cta_border_color',
            'scenes.*.id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,50}$/', 'distinct'],
            'scenes.*.text' => 'required|string|max:500',
            'scenes.*.duration' => 'required|numeric|min:1|max:45',
            'scenes.*.media_id' => 'present|nullable|integer|exists:media,id,deleted_at,NULL',
            'scenes.*.media_mode' => ['sometimes', Rule::in(SceneDesignAgent::MEDIA_MODES)],
            'scenes.*.media_locked' => 'sometimes|boolean',
            'scenes.*.media_type' => ['sometimes', Rule::in(SceneDesignAgent::MEDIA_TYPES)],
            'scenes.*.media_fit' => ['sometimes', Rule::in(SceneDesignAgent::MEDIA_FITS)],
            'scenes.*.media_focus' => ['sometimes', Rule::in(SceneDesignAgent::MEDIA_FOCUSES)],
            'scenes.*.overlay_mode' => ['sometimes', Rule::in(SceneDesignAgent::OVERLAY_MODES)],
            'scenes.*.media_origin' => ['sometimes', Rule::in(SceneDesignAgent::MEDIA_ORIGINS)],
            'scenes.*.purpose' => ['sometimes', Rule::in(SceneDesignAgent::PURPOSES)],
            'scenes.*.headline' => 'sometimes|string|max:140',
            'scenes.*.body' => 'sometimes|string|max:500',
            'scenes.*.headline_font_size' => 'sometimes|integer|between:68,104',
            'scenes.*.body_font_size' => 'sometimes|integer|between:36,60',
            'scenes.*.headline_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'scenes.*.body_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'scenes.*.text_font_weight' => ['sometimes', Rule::in(['regular', 'medium', 'semibold', 'bold', 'extra_bold'])],
            'scenes.*.text_stroke' => ['sometimes', Rule::in(['none', 'thin', 'medium', 'thick'])],
            'scenes.*.text_stroke_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'scenes.*.text_shadow' => ['sometimes', Rule::in(['none', 'soft', 'medium', 'strong'])],
            'scenes.*.cta_text_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'scenes.*.cta_background_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'scenes.*.cta_border_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'scenes.*.visual_style' => ['sometimes', Rule::in(SceneDesignAgent::STYLES)],
            'scenes.*.background_type' => ['sometimes', Rule::in(SceneDesignAgent::BACKGROUNDS)],
            'scenes.*.animation' => ['sometimes', Rule::in(SceneDesignAgent::ANIMATIONS)],
            'scenes.*.layout' => ['sometimes', Rule::in(SceneDesignAgent::LAYOUTS)],
            'scenes.*.template' => ['sometimes', Rule::in(SceneDesignAgent::TEMPLATES)],
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
        foreach ($validated['scenes'] as $i => $scene) {
            if (($scene['media_mode'] ?? null) === 'manual' && !$scene['media_id']) throw ValidationException::withMessages(["scenes.$i.media_id" => 'Manual backgrounds require a selected image or video.']);
            if (($scene['media_mode'] ?? null) === 'manual' && $scene['media_id']) $validated['scenes'][$i]['background_type'] = 'media';
            if (!$scene['media_id']) continue;
            $asset = Media::find($scene['media_id']);
            if (!$asset || $asset->folder !== 'affiliate-agent' || !in_array($asset->mime_type, ['video/mp4', 'image/png', 'image/jpeg', 'image/webp'], true)) throw ValidationException::withMessages(["scenes.$i.media_id" => 'Choose an Affiliate Agent image or MP4.']);
            if (isset($scene['media_type']) && $scene['media_type'] !== ($asset->mime_type === 'video/mp4' ? 'video' : 'image')) throw ValidationException::withMessages(["scenes.$i.media_type" => 'Media type must match the selected asset.']);
        }
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
