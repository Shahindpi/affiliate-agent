<?php

namespace App\Services\AffiliateAgent;

class SceneDesignAgent
{
    public const PURPOSES = ['hook', 'problem', 'benefit', 'demo', 'closing'];
    public const STYLES = ['technology', 'nature', 'cinematic', 'product', 'abstract', 'minimal'];
    public const BACKGROUNDS = ['media', 'animated_abstract', 'solid_light', 'solid_dark'];
    public const MEDIA_MODES = ['auto', 'manual'];
    public const MEDIA_ORIGINS = ['auto', 'manual', 'uploaded', 'fallback'];
    public const MEDIA_TYPES = ['image', 'video'];
    public const MEDIA_FITS = ['cover', 'contain'];
    public const MEDIA_FOCUSES = ['center', 'top', 'bottom', 'left', 'right'];
    public const OVERLAY_MODES = ['auto', 'light', 'medium', 'strong', 'none'];
    public const ANIMATIONS = ['fade_up', 'slide_in', 'scale_in', 'masked_reveal', 'staggered', 'typewriter', 'cursor_reveal', 'fade_in', 'fade_in_out', 'slide_up', 'slide_left', 'slide_right', 'scale_pop', 'word_stagger'];
    public const LAYOUTS = ['left', 'center', 'split'];
    public const TEMPLATES = [
        'none', 'link_in_bio', 'check_description', 'try_it_here', 'learn_more', 'visit_website',
        'use_link_below', 'see_full_details', 'explore_now', 'cta_badge', 'cta_button',
        'affiliate_disclaimer', 'product_title', 'feature_highlight', 'bullet_point',
        'social_outro', 'closing_end_card', 'link_direction', 'powered_by_dewdora', 'lower_third', 'tag_badge',
    ];
    private const LABELS = [
        'link_in_bio' => 'Link in bio', 'check_description' => 'Check description', 'try_it_here' => 'Try it here',
        'learn_more' => 'Learn more', 'visit_website' => 'Visit website', 'use_link_below' => 'Use the link below',
        'see_full_details' => 'See full details', 'explore_now' => 'Explore now',
    ];

    public function normalize(array $scenes, array $previous, bool $preserveStyle = true): array
    {
        $fields = [
            'purpose' => self::PURPOSES, 'visual_style' => self::STYLES,
            'background_type' => self::BACKGROUNDS, 'animation' => self::ANIMATIONS,
            'layout' => self::LAYOUTS, 'template' => self::TEMPLATES,
        ];
        $old = array_column($previous, null, 'id');
        foreach ($scenes as &$scene) {
            if ($preserveStyle) foreach (['headline_font_size', 'body_font_size', 'headline_color', 'body_color', 'cta_text_color', 'cta_background_color', 'cta_border_color'] as $field) {
                if (!array_key_exists($field, $scene) && isset($old[$scene['id'] ?? ''][$field])) $scene[$field] = $old[$scene['id']][$field];
            }
            foreach ($fields as $field => $options) {
                if (!array_key_exists($field, $scene) || in_array($scene[$field], $options, true)) continue;
                $raw = is_string($scene[$field]) ? strtolower($scene[$field]) : '';
                $choice = collect($options)->first(fn ($option) => str_contains($raw, str_replace('_', ' ', $option)) || str_contains($raw, $option));
                if (!$choice && $field === 'animation' && preg_match('/fad(?:e|es|ing).*up/', $raw)) $choice = 'fade_up';
                if ($choice) $scene[$field] = $choice;
                elseif (isset($old[$scene['id'] ?? ''][$field])) $scene[$field] = $old[$scene['id']][$field];
                else unset($scene[$field]);
            }
        }
        return $scenes;
    }

    public function decorate(array $scenes, int $contentId, string $topic = ''): array
    {
        $count = count($scenes);
        $nature = preg_match('/garden|nature|plant|outdoor|travel|wellness|eco/i', $topic) === 1;
        $lastAnimation = null;
        foreach ($scenes as $i => &$scene) {
            $purpose = $scene['purpose'] ?? ($i === 0 ? 'hook' : ($i === $count - 1 ? 'closing' : (preg_match('/demo|workflow|how/i', $scene['id']) ? 'demo' : 'benefit')));
            $style = $scene['visual_style'] ?? match ($purpose) {
                'demo' => 'product', 'hook' => $nature ? 'nature' : (['technology', 'cinematic', 'abstract'][($contentId + $i) % 3]),
                'closing' => 'cinematic', default => $nature ? 'nature' : (['technology', 'cinematic'][($contentId + $i) % 2]),
            };
            $choices = match ($purpose) {
                'hook' => ['typewriter', 'masked_reveal', 'scale_pop', 'fade_up'],
                'demo' => ['slide_left', 'fade_in', 'slide_up'],
                'closing' => ['scale_pop', 'fade_in_out', 'slide_up'],
                default => ['slide_up', 'word_stagger', 'staggered', 'fade_up'],
            };
            $animation = $choices[($contentId + $i) % count($choices)];
            if ($animation === $lastAnimation) $animation = $choices[($contentId + $i + 1) % count($choices)];
            if (mb_strlen($scene['text']) > 90 && in_array($animation, ['typewriter', 'cursor_reveal'], true)) $animation = 'fade_up';
            $scene += [
                'purpose' => $purpose, 'headline' => mb_substr($scene['text'], 0, 110), 'body' => '',
                'visual_style' => $style, 'background_type' => empty($scene['media_id']) ? 'animated_abstract' : 'media',
                'animation' => $animation,
                'layout' => self::LAYOUTS[($contentId + $i) % count(self::LAYOUTS)],
                'template' => $purpose === 'closing' ? 'cta_button' : ($purpose === 'demo' ? 'product_title' : 'none'),
            ];
            $lastAnimation = $scene['animation'];
        }
        return $scenes;
    }

    public function templateText(string $key, string $cta): string
    {
        return self::LABELS[$key] ?? match ($key) {
            'none' => '', 'powered_by_dewdora' => 'Powered by Dewdora',
            'affiliate_disclaimer' => 'Affiliate link',
            'product_title' => 'PRODUCT', 'feature_highlight' => 'FEATURE', 'bullet_point' => 'HIGHLIGHT',
            'lower_third' => 'QUICK LOOK', 'tag_badge' => 'DISCOVER',
            default => $cta,
        };
    }
}
