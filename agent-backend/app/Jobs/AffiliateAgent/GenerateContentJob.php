<?php

namespace App\Jobs\AffiliateAgent;

use App\Models\Agent\Content;
use App\Services\AffiliateAgent\ComplianceQaAgent;
use App\Services\AffiliateAgent\ContentSchema;
use App\Services\AffiliateAgent\MediaSelectionAgent;
use App\Services\AffiliateAgent\PlatformMetadataAgent;
use App\Services\AffiliateAgent\PreferenceService;
use App\Services\AffiliateAgent\Providers\AIProviderInterface;
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

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(
        public int $contentId,
        public array $snapshot,
        public int $userId
    ) {
        $this->onQueue('agent-generation');
    }

    public function handle(
        RevisionAgent $agent,
        ReviewService $review
    ): void {
        Cache::lock('agent-initial:'.$this->contentId, 900)
            ->block(5, function () use ($agent, $review) {
                $c = Content::findOrFail($this->contentId);

                // Do not regenerate content that already has a version.
                if ($c->current_version_id) {
                    return;
                }

                $checkpoint = $c->checkpoint ?? [];
                $snapshot = $checkpoint['snapshot'] ?? $this->snapshot;

                /*
                 * Initial metadata normalization.
                 */
                if (!$c->checkpoint) {
                    $defaults = app(PlatformMetadataAgent::class)->create(
                        $c->title,
                        $snapshot,
                        $c->product?->affiliate_url ?: ''
                    );

                    foreach (ContentSchema::PLATFORMS as $platform) {
                        if (empty($snapshot['metadata'][$platform]['hashtags'])) {
                            $snapshot['metadata'][$platform]['hashtags']
                                = $defaults[$platform]['hashtags'];
                        }

                        if (
                            empty($snapshot['metadata'][$platform]['affiliate_url'])
                            && $c->product?->affiliate_url
                        ) {
                            $snapshot['metadata'][$platform]['affiliate_url']
                                = $c->product->affiliate_url;
                        }
                    }

                    $snapshot = app(ContentSchema::class)->validate($snapshot);
                }

                /*
                 * Apply configured preferences.
                 *
                 * IMPORTANT:
                 * Manually selected or locked scene media always wins.
                 *
                 * Preferences may still modify text, typography, colors,
                 * animation, CTA styling, etc., but they may NOT replace
                 * protected media fields.
                 */
                $preferences = app(PreferenceService::class)->applicable($c);

                if (
                    !$c->checkpoint
                    && $preferences
                    && config('affiliate_agent.provider') !== 'mock'
                ) {
                    $patch = app(AIProviderInterface::class)->revise(
                        $snapshot,
                        'Apply these explicitly configured preferences to this new content only: '
                            .json_encode($preferences),
                        'all',
                        [],
                        $c->id
                    );

                    /*
                     * Preferences may modify normal content components,
                     * but never the generated video artifact itself.
                     */
                    $allowedComponents = array_diff(
                        ContentSchema::COMPONENTS,
                        ['video']
                    );

                    if (array_diff(array_keys($patch), $allowedComponents)) {
                        throw new \RuntimeException(
                            'Invalid preference patch.'
                        );
                    }

                    /*
                     * Preserve manual / locked media instead of failing
                     * generation when AI preferences propose different media.
                     */
                    if (
                        isset($patch['scenes'])
                        && is_array($patch['scenes'])
                        && isset($snapshot['scenes'])
                        && is_array($snapshot['scenes'])
                    ) {
                        $originalScenes = [];

                        foreach ($snapshot['scenes'] as $scene) {
                            if (isset($scene['id'])) {
                                $originalScenes[(string) $scene['id']] = $scene;
                            }
                        }

                        $protectedMediaFields = [
                            'media_id',
                            'media_mode',
                            'media_locked',
                            'media_type',
                            'media_fit',
                            'media_focus',
                            'overlay_mode',
                            'media_origin',
                            'background_type',
                        ];

                        foreach ($patch['scenes'] as &$candidate) {
                            if (!isset($candidate['id'])) {
                                continue;
                            }

                            $sceneId = (string) $candidate['id'];
                            $original = $originalScenes[$sceneId] ?? null;

                            if (!$original) {
                                continue;
                            }

                            $manualMedia = (
                                ($original['media_mode'] ?? null) === 'manual'
                                && !empty($original['media_id'])
                            );

                            $lockedMedia = !empty(
                                $original['media_locked']
                            );

                            if (!$manualMedia && !$lockedMedia) {
                                continue;
                            }

                            /*
                             * Keep every original protected media value.
                             *
                             * If the original field did not exist, remove
                             * anything the preference patch attempted to add.
                             */
                            foreach ($protectedMediaFields as $field) {
                                if (array_key_exists($field, $original)) {
                                    $candidate[$field] = $original[$field];
                                } else {
                                    unset($candidate[$field]);
                                }
                            }
                        }

                        unset($candidate);
                    }

                    /*
                     * Apply the sanitized preference patch.
                     */
                    $snapshot = app(ContentSchema::class)->validate(
                        array_replace($snapshot, $patch)
                    );
                }

                /*
                 * Automatic media selection.
                 *
                 * MediaSelectionAgent must continue to skip scenes whose
                 * media_mode is manual or media_locked is enabled.
                 */
                if (!$c->checkpoint) {
                    $snapshot['scenes'] = app(MediaSelectionAgent::class)->select(
                        $snapshot['scenes'],
                        $c->product?->name ?? '',
                        $c->brand?->name ?? '',
                        false,
                        $c->product?->category?->name ?? ''
                    );

                    $snapshot = app(ContentSchema::class)->validate($snapshot);
                }

                /*
                 * Persist checkpoint before expensive voice/render work.
                 */
                $checkpoint = $c->checkpoint ?? [];

                $c->update([
                    'checkpoint' => [
                        ...$checkpoint,
                        'snapshot' => $snapshot,
                    ],
                ]);

                /*
                 * Voice + video rendering.
                 */
                $artifacts = $agent->produce(
                    $snapshot,
                    $checkpoint['artifacts'] ?? [],
                    ['voice', 'render'],
                    $c->id,
                    null,
                    $checkpoint['completed'] ?? [],
                    function ($assets, $completed) use ($c, &$snapshot) {
                        $c->update([
                            'checkpoint' => [
                                'snapshot' => $snapshot,
                                'artifacts' => $assets,
                                'completed' => $completed,
                            ],
                        ]);
                    }
                );

                /*
                 * Final QA.
                 */
                $qa = app(ComplianceQaAgent::class)->check(
                    $snapshot,
                    $artifacts
                );

                /*
                 * Create immutable review version.
                 */
                DB::transaction(function () use (
                    $review,
                    $artifacts,
                    $qa,
                    $snapshot
                ) {
                    $c = Content::lockForUpdate()
                        ->findOrFail($this->contentId);

                    if (!$c->current_version_id) {
                        $review->append(
                            $c,
                            [
                                'snapshot' => $snapshot,
                                'artifacts' => $artifacts,
                                'qa' => $qa,
                                'changes' => [],
                                'steps' => [
                                    'voice',
                                    'render',
                                    'qa',
                                ],
                            ],
                            $this->userId
                        );
                    }
                });
            });
    }

    public function failed(?\Throwable $e): void
    {
        Content::where('id', $this->contentId)
            ->whereNull('current_version_id')
            ->update([
                'status' => 'GENERATION_FAILED',
            ]);
    }
}