<?php

namespace App\Services\AffiliateAgent;

use App\Models\Agent\Content;
use App\Models\Agent\Feedback;
use App\Services\AffiliateAgent\Providers\AIProviderInterface;
use Illuminate\Validation\ValidationException;

class RevisionAgent
{
    public function __construct(private AIProviderInterface $provider, private ContentSchema $schema, private RevisionPlanner $planner) {}

    public function execute(Feedback $feedback): array
    {
        $content = Content::findOrFail($feedback->content_id);
        $base = $content->versions()->findOrFail($feedback->base_version_id);
        if ($content->current_version_id !== $base->id || $content->locks !== $feedback->locks) throw ValidationException::withMessages(['version' => 'Content or locks changed while the revision was queued. Resubmit against the current version.']);
        $adminPatch = $feedback->patch !== null;
        $mediaRevision = app(SceneMediaRevision::class);
        $patch = $feedback->patch ?? (config('affiliate_agent.provider') === 'mock' && $mediaRevision->hasIntent($feedback->feedback)
            ? ['scenes' => $base->snapshot['scenes']]
            : $this->provider->revise($base->snapshot, $feedback->feedback, $feedback->target, $feedback->locks, $content->id));
        if (!$adminPatch && $mediaRevision->hasIntent($feedback->feedback) && !isset($patch['scenes'])) $patch['scenes'] = $base->snapshot['scenes'];
        if (!$patch || array_diff(array_keys($patch), array_diff(ContentSchema::COMPONENTS, ['video']))) throw ValidationException::withMessages(['patch' => 'Invalid revision components.']);
        if (isset($patch['scenes']) && is_array($patch['scenes'])) {
            $patch['scenes'] = app(SceneDesignAgent::class)->normalize($patch['scenes'], $base->snapshot['scenes'], !$adminPatch);
            if (!$adminPatch) $patch['scenes'] = $mediaRevision->apply($base->snapshot['scenes'], $patch['scenes'], $feedback->feedback, false);
        }
        $feedback->update(['patch' => $patch]);
        $proposed = $this->schema->validate(array_replace($base->snapshot, $patch));
        if ($adminPatch) $proposed['scenes'] = $mediaRevision->apply($base->snapshot['scenes'], $proposed['scenes'], $feedback->feedback, true);
        $proposed = $this->schema->validate($proposed);
        // Enforce selected scope before allowing deterministic dependent steps.
        foreach ($this->planner->changes($base->snapshot, $proposed) as $change) {
            if ($feedback->target !== 'all' && $change['component'] !== $feedback->target && !str_starts_with($change['component'], $feedback->target.'.')) throw ValidationException::withMessages(['target' => 'The provider changed content outside the requested scope.']);
        }
        // Reject locked changes before enriching any scene. An explicit media ID is never replaced.
        $this->planner->plan($base->snapshot, $proposed, $feedback->locks);
        if (isset($patch['scenes'])) {
            $before = array_column($base->snapshot['scenes'], null, 'id');
            $visualFeedback = preg_match('/visual|background|footage|screenshot|image|video|media/i', $feedback->feedback) === 1;
            foreach ($proposed['scenes'] as &$scene) {
                $old = $before[$scene['id']] ?? null;
                if ($old === $scene) continue;
                $scene = app(SceneDesignAgent::class)->decorate([$scene], $content->id, $content->product?->name ?? $content->title)[0];
                $reselect = ($scene['media_mode'] ?? 'auto') === 'auto' && ($scene['background_type'] === 'media' || ($visualFeedback && $scene['background_type'] === 'animated_abstract'
                    && ($old['background_type'] ?? null) === 'animated_abstract' && ($old['visual_style'] ?? null) !== $scene['visual_style']));
                if ($scene['media_id'] === null && $reselect) {
                    $selected = app(MediaSelectionAgent::class)->select([$scene], $content->product?->name ?? '', $content->brand?->name ?? '', true, $content->product?->category?->name ?? '')[0];
                    if ($selected['media_id'] !== null) {
                        $scene = $selected;
                        $scene['background_type'] = 'media';
                    } elseif ($scene['background_type'] === 'media') $scene = $selected;
                }
            }
            unset($scene);
        }
        $plan = $this->planner->plan($base->snapshot, $proposed, $feedback->locks);
        $plan['snapshot'] = $this->schema->validate($plan['snapshot']);
        $checkpoint = $feedback->checkpoint ?? [];
        $snapshot = $plan['snapshot'];
        $artifacts = $this->produce($snapshot, $checkpoint['artifacts'] ?? $base->artifacts, $plan['steps'], $content->id, $base->snapshot, $checkpoint['completed'] ?? [], function ($assets, $completed) use ($feedback) { $feedback->update(['checkpoint' => ['artifacts' => $assets, 'completed' => $completed]]); }, $feedback->locks);
        if ($snapshot !== $plan['snapshot']) $plan = $this->planner->plan($base->snapshot, $this->schema->validate($snapshot), $feedback->locks);
        $qa = app(ComplianceQaAgent::class)->check($plan['snapshot'], $artifacts);
        return [...$plan, 'artifacts' => $artifacts, 'qa' => $qa, 'mock' => config('affiliate_agent.provider') === 'mock'];
    }

    public function produce(array &$snapshot, array $artifacts, array $steps, int $contentId, ?array $base = null, array $completed = [], ?callable $checkpoint = null, array $locks = []): array
    {
        $sceneAssets = [];
        foreach ($snapshot['scenes'] as $scene) {
            if (!$scene['media_id']) continue;
            $previous = $base ? collect($base['scenes'])->firstWhere('id', $scene['id']) : null;
            $sceneAssets[$scene['id']] = isset($artifacts['scenes'][$scene['id']]) && (in_array('voice', $completed, true) || in_array('render', $completed, true) || ($previous && $previous['media_id'] === $scene['media_id']))
                ? $artifacts['scenes'][$scene['id']] : app(AssetStore::class)->copyMedia($scene['media_id']);
        }
        $artifacts['scenes'] = $sceneAssets;
        if ($checkpoint) $checkpoint($artifacts, $completed);
        if (in_array('voice', $steps, true) && !in_array('voice', $completed, true)) {
            $artifacts['voice'] = app(VoiceAgent::class)->generate($snapshot, $contentId);
            $completed[] = 'voice';
            if ($checkpoint) $checkpoint($artifacts, $completed);
        }
        if (in_array('render', $steps, true) && !in_array('render', $completed, true)) {
            // A base video artifact is never evidence of a completed revised render.
            unset($artifacts['video']);
            $artifacts['video'] = app(VideoComposerAgent::class)->render($snapshot, $artifacts, $contentId, $locks);
            $completed[] = 'render';
            if ($checkpoint) $checkpoint($artifacts, $completed);
        }
        return $artifacts;
    }
}
