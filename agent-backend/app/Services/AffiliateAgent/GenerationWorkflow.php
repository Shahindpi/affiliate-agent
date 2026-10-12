<?php

namespace App\Services\AffiliateAgent;

use App\Models\Agent\Content;
use App\Models\Agent\Setting;

class GenerationWorkflow
{
    public function launch(Setting $settings, int $userId): Content
    {
        $plan = app(ContentPlannerAgent::class)->next($settings);
        $title = app(IdeaAgent::class)->propose($plan);
        $research = app(ResearchAgent::class)->context($plan['product']);
        if ($plan['campaign']) $research['campaign_brief'] = $plan['campaign']->brief;
        // Content row exists before provider calls so model usage has a durable content ID.
        $content = Content::create(['title' => $title, 'content_type' => $plan['type'], 'planned_for' => now($settings->timezone)->toDateString(), 'brand_id' => $plan['product']->brand_id, 'affiliate_product_id' => $plan['product']->id, 'campaign_id' => $plan['campaign']?->id, 'created_by' => $userId, 'locks' => [], 'status' => 'GENERATING']);
        try {
            $draft = app(ScriptAgent::class)->draft($title, $research, $settings->duration_seconds, $content->id);
            // Scene style and purpose must exist before matching nature, technology or demo footage.
            $planned = array_map(function ($scene) {
                if (!array_key_exists('background_type', $scene) && empty($scene['media_id'])) $scene['background_type'] = 'media';
                return $scene;
            }, $draft['scenes']);
            $scenes = app(SceneDesignAgent::class)->decorate($planned, $content->id, $plan['product']->name.' '.$title);
            $scenes = app(MediaSelectionAgent::class)->select($scenes, $plan['product']->name, $plan['product']->brand?->name ?? '', false, $plan['product']->category?->name ?? '');
            $draft['script'] = VoiceAgent::narration(['scenes' => $scenes]);
            $draft['captions'] = $draft['script'];
            $snapshot = app(ContentSchema::class)->validate([...$draft, 'scenes' => $scenes, 'voice' => ['voice_id' => null], 'metadata' => app(PlatformMetadataAgent::class)->create($title, $draft, $research['affiliate_url'])]);
            \App\Jobs\AffiliateAgent\GenerateContentJob::dispatch($content->id, $snapshot, $userId);
        } catch (\Throwable $e) { $content->update(['status' => 'GENERATION_FAILED']); throw $e; }
        return $content;
    }
}
