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
        $patch = $feedback->patch ?? $this->provider->revise($base->snapshot, $feedback->feedback, $feedback->target, $feedback->locks, $content->id);
        if (!$patch || array_diff(array_keys($patch), array_diff(ContentSchema::COMPONENTS, ['video']))) throw ValidationException::withMessages(['patch' => 'Invalid revision components.']);
        $feedback->update(['patch' => $patch]);
        $proposed = $this->schema->validate(array_replace($base->snapshot, $patch));
        // Enforce selected scope before allowing deterministic dependent steps.
        foreach ($this->planner->changes($base->snapshot, $proposed) as $change) {
            if ($feedback->target !== 'all' && $change['component'] !== $feedback->target && !str_starts_with($change['component'], $feedback->target.'.')) throw ValidationException::withMessages(['target' => 'The provider changed content outside the requested scope.']);
        }
        $plan = $this->planner->plan($base->snapshot, $proposed, $feedback->locks);
        $plan['snapshot'] = $this->schema->validate($plan['snapshot']);
        $checkpoint = $feedback->checkpoint ?? [];
        $artifacts = $this->produce($plan['snapshot'], $checkpoint['artifacts'] ?? $base->artifacts, $plan['steps'], $content->id, $base->snapshot, $checkpoint['completed'] ?? [], function ($assets, $completed) use ($feedback) { $feedback->update(['checkpoint' => ['artifacts' => $assets, 'completed' => $completed]]); });
        $qa = app(ComplianceQaAgent::class)->check($plan['snapshot'], $artifacts);
        return [...$plan, 'artifacts' => $artifacts, 'qa' => $qa, 'mock' => config('affiliate_agent.provider') === 'mock'];
    }

    public function produce(array $snapshot, array $artifacts, array $steps, int $contentId, ?array $base = null, array $completed = [], ?callable $checkpoint = null): array
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
            $artifacts['video'] = app(VideoComposerAgent::class)->render($snapshot, $artifacts, $contentId);
            $completed[] = 'render';
            if ($checkpoint) $checkpoint($artifacts, $completed);
        }
        return $artifacts;
    }
}
