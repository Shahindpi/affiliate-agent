<?php

namespace App\Jobs\AffiliateAgent;

use App\Models\Agent\Content;
use App\Services\AffiliateAgent\ComplianceQaAgent;
use App\Services\AffiliateAgent\RevisionAgent;
use App\Services\AffiliateAgent\ReviewService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GenerateContentJob implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public int $timeout = 850;
    public function backoff(): array { return [30, 120, 300]; }
    public function __construct(public int $contentId, public array $snapshot, public int $userId) { $this->onQueue('agent-revisions'); }
    public function handle(RevisionAgent $agent, ReviewService $review): void
    {
        Cache::lock('agent-initial:'.$this->contentId, 900)->block(5, function () use ($agent, $review) {
            $c = Content::findOrFail($this->contentId);
            if ($c->current_version_id) return;
            $snapshot = $c->checkpoint['snapshot'] ?? $this->snapshot;
            if (!$c->checkpoint) {
                $defaults = app(\App\Services\AffiliateAgent\PlatformMetadataAgent::class)->create($c->title, $snapshot, $c->product?->affiliate_url ?: '');
                foreach (\App\Services\AffiliateAgent\ContentSchema::PLATFORMS as $platform) {
                    if (empty($snapshot['metadata'][$platform]['hashtags'])) $snapshot['metadata'][$platform]['hashtags'] = $defaults[$platform]['hashtags'];
                    if (empty($snapshot['metadata'][$platform]['affiliate_url']) && $c->product?->affiliate_url) $snapshot['metadata'][$platform]['affiliate_url'] = $c->product->affiliate_url;
                }
                $snapshot = app(\App\Services\AffiliateAgent\ContentSchema::class)->validate($snapshot);
            }
            $preferences = app(\App\Services\AffiliateAgent\PreferenceService::class)->applicable($c);
            if (!$c->checkpoint && $preferences && config('affiliate_agent.provider') !== 'mock') {
                $patch = app(\App\Services\AffiliateAgent\Providers\AIProviderInterface::class)->revise($snapshot, 'Apply these explicitly configured preferences to this new content only: '.json_encode($preferences), 'all', [], $c->id);
                if (array_diff(array_keys($patch), array_diff(\App\Services\AffiliateAgent\ContentSchema::COMPONENTS, ['video']))) throw new \RuntimeException('Invalid preference patch.');
                $snapshot = app(\App\Services\AffiliateAgent\ContentSchema::class)->validate(array_replace($snapshot, $patch));
            }
            $checkpoint = $c->checkpoint ?? [];
            $c->update(['checkpoint' => [...$checkpoint, 'snapshot' => $snapshot]]);
            $artifacts = $agent->produce($snapshot, $checkpoint['artifacts'] ?? [], ['voice', 'render'], $c->id, null, $checkpoint['completed'] ?? [], function ($assets, $completed) use ($c, $snapshot) { $c->update(['checkpoint' => ['snapshot' => $snapshot, 'artifacts' => $assets, 'completed' => $completed]]); });
            $qa = app(ComplianceQaAgent::class)->check($snapshot, $artifacts);
            DB::transaction(function () use ($review, $artifacts, $qa, $snapshot) {
                $c = Content::lockForUpdate()->findOrFail($this->contentId);
                if (!$c->current_version_id) $review->append($c, ['snapshot' => $snapshot, 'artifacts' => $artifacts, 'qa' => $qa, 'changes' => [], 'steps' => ['voice', 'render', 'qa']], $this->userId);
            });
        });
    }
    public function failed(?\Throwable $e): void { Content::where('id', $this->contentId)->whereNull('current_version_id')->update(['status' => 'GENERATION_FAILED']); }
}
