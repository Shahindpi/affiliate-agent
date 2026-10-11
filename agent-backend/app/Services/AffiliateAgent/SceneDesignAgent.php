<?php

namespace App\Services\AffiliateAgent;

class SceneDesignAgent
{
    public const PURPOSES = ['hook', 'problem', 'benefit', 'demo', 'closing'];
    public const STYLES = ['technology', 'nature', 'cinematic', 'product', 'abstract', 'minimal'];
    public const BACKGROUNDS = ['media', 'animated_abstract', 'solid_light', 'solid_dark'];
    public const ANIMATIONS = ['fade_up', 'slide_in', 'scale_in', 'masked_reveal', 'staggered'];
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

    public function normalize(array $scenes, array $previous): array
    {
        $fields = [
            'purpose' => self::PURPOSES, 'visual_style' => self::STYLES,
            'background_type' => self::BACKGROUNDS, 'animation' => self::ANIMATIONS,
            'layout' => self::LAYOUTS, 'template' => self::TEMPLATES,
        ];
        $old = array_column($previous, null, 'id');
        foreach ($scenes as &$scene) {
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
        foreach ($scenes as $i => &$scene) {
            $purpose = $scene['purpose'] ?? ($i === 0 ? 'hook' : ($i === $count - 1 ? 'closing' : (preg_match('/demo|workflow|how/i', $scene['id']) ? 'demo' : 'benefit')));
            $style = $scene['visual_style'] ?? match ($purpose) {
                'demo' => 'product', 'hook' => $nature ? 'nature' : (['technology', 'cinematic', 'abstract'][($contentId + $i) % 3]),
                'closing' => 'cinematic', default => $nature ? 'nature' : (['technology', 'cinematic'][($contentId + $i) % 2]),
            };
            $scene += [
                'purpose' => $purpose, 'headline' => mb_substr($scene['text'], 0, 110), 'body' => '',
                'visual_style' => $style, 'background_type' => empty($scene['media_id']) ? 'animated_abstract' : 'media',
                'animation' => self::ANIMATIONS[($contentId + $i) % count(self::ANIMATIONS)],
                'layout' => self::LAYOUTS[($contentId + $i) % count(self::LAYOUTS)],
                'template' => $purpose === 'closing' ? 'cta_button' : ($purpose === 'demo' ? 'product_title' : 'none'),
            ];
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
