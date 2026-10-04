<?php

namespace Tests\Feature;

use App\Jobs\AffiliateAgent\GenerateContentJob;
use App\Jobs\AffiliateAgent\ReviseContentJob;
use App\Models\Agent\Content;
use App\Models\Agent\ContentVersion;
use App\Models\Agent\Feedback;
use App\Models\Agent\Preference;
use App\Models\Agent\Publication;
use App\Models\AffiliateProduct;
use App\Models\Brand;
use App\Models\Role;
use App\Models\User;
use App\Services\AffiliateAgent\AssetStore;
use App\Services\AffiliateAgent\ComplianceQaAgent;
use App\Services\AffiliateAgent\ContentSchema;
use App\Services\AffiliateAgent\PreferenceService;
use App\Services\AffiliateAgent\Providers\AIProviderInterface;
use App\Services\AffiliateAgent\PublishingAgent;
use App\Services\AffiliateAgent\ReviewService;
use App\Services\AffiliateAgent\RevisionAgent;
use App\Services\AffiliateAgent\VideoComposerAgent;
use App\Services\AffiliateAgent\VoiceAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentReviewTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private string $root = '/api/v1/admin/affiliate-agent';

    protected function setUp(): void
    {
        parent::setUp();
        config(['affiliate_agent.provider' => 'mock', 'affiliate_agent.voice_provider' => 'mock']);
        Storage::fake('local');
        Queue::fake();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'status' => true]);
        $this->admin = User::create(['name' => 'Reviewer', 'username' => 'reviewer', 'email' => 'reviewer@example.test', 'password' => 'Password123!', 'role_id' => $role->id, 'status' => true]);
        Sanctum::actingAs($this->admin);
    }

    private function snapshot(): array
    {
        $metadata = [];
        foreach (ContentSchema::PLATFORMS as $p) $metadata[$p] = ['title' => 'Tutorial', 'caption' => 'See how it works', 'description' => 'A practical tutorial', 'hashtags' => ['#tutorial'], 'affiliate_url' => 'https://example.test/offer', 'cta' => 'Try the demo', 'disclosure' => 'Affiliate link: I may earn a commission.'];
        return ['script' => 'Turn text into a voice. Try this practical demo.', 'captions' => 'Turn text into a voice. Try this practical demo.', 'cta' => 'Try the demo', 'disclosure' => 'Affiliate link: I may earn a commission.', 'voice' => ['voice_id' => null], 'scenes' => [['id' => 'intro', 'text' => 'Turn text into a voice', 'duration' => 7.5, 'media_id' => null], ['id' => 'demo', 'text' => 'Paste text and generate', 'duration' => 7.5, 'media_id' => null]], 'metadata' => $metadata];
    }

    private function content(bool $passed = true): Content
    {
        $c = Content::create(['title' => 'Tutorial video', 'created_by' => $this->admin->id, 'locks' => [], 'status' => 'GENERATING']);
        $artifacts = ['voice' => app(AssetStore::class)->put('test voice fixture', 'wav', 'audio/wav', true), 'video' => app(AssetStore::class)->put('test video fixture', 'mp4', 'video/mp4', true), 'scenes' => []];
        app(ReviewService::class)->append($c, ['snapshot' => $this->snapshot(), 'artifacts' => $artifacts, 'steps' => ['voice', 'render', 'qa'], 'changes' => [], 'qa' => ['passed' => $passed, 'checks' => ['dimensions' => $passed]]], $this->admin->id);
        return $c->fresh();
    }

    private function qa(): void
    {
        $this->mock(ComplianceQaAgent::class, fn ($m) => $m->shouldReceive('check')->andReturn(['passed' => true, 'checks' => ['dimensions' => true]]));
    }

    private function execute(int $feedbackId): void
    {
        (new ReviseContentJob($feedbackId))->handle(app(RevisionAgent::class), app(ReviewService::class));
    }

    public function test_creation_is_queued_and_cannot_publish_before_review(): void
    {
        $id = $this->postJson($this->root.'/contents', ['title' => 'New master', 'snapshot' => $this->snapshot()])->assertCreated()->assertJsonPath('data.status', 'GENERATING')->json('data.id');
        Queue::assertPushed(GenerateContentJob::class);
        $this->postJson($this->root.'/contents/'.$id.'/publications', ['expected_version_id' => 1, 'platform' => 'youtube', 'mode' => 'manual'])->assertConflict();
    }

    public function test_initial_content_accepts_missing_hashtags_and_metadata_then_generates_them(): void
    {
        $brand = Brand::create(['name' => 'ElevenLabs', 'slug' => 'elevenlabs-hashtags']);
        $product = AffiliateProduct::create(['name' => 'Text to Speech', 'slug' => 'tts-hashtags', 'brand_id' => $brand->id, 'affiliate_url' => 'https://example.test/affiliate']);
        $snapshot = $this->snapshot();
        foreach (ContentSchema::PLATFORMS as $platform) unset($snapshot['metadata'][$platform]['hashtags']);
        $this->postJson($this->root.'/contents', ['title' => 'Missing hashtags', 'snapshot' => $snapshot])->assertCreated();
        unset($snapshot['metadata']);
        $id = $this->postJson($this->root.'/contents', ['title' => 'Missing all platform metadata', 'brand_id' => $brand->id, 'affiliate_product_id' => $product->id, 'snapshot' => $snapshot])->assertCreated()->json('data.id');
        $c = Content::findOrFail($id);
        $this->assertSame([], $c->checkpoint['snapshot']['metadata']['pinterest']['hashtags'] ?? []);
        $this->qa();
        $voice = app(AssetStore::class)->put('audio', 'wav', 'audio/wav', true);
        $video = app(AssetStore::class)->put('video', 'mp4', 'video/mp4', true);
        $this->mock(VoiceAgent::class, fn ($m) => $m->shouldReceive('generate')->andReturn($voice));
        $this->mock(VideoComposerAgent::class, fn ($m) => $m->shouldReceive('render')->andReturn($video));
        (new GenerateContentJob($id, app(ContentSchema::class)->validate($snapshot), $this->admin->id))->handle(app(RevisionAgent::class), app(ReviewService::class));
        $this->assertSame(['#pinterest'], $c->fresh()->currentVersion->snapshot['metadata']['pinterest']['hashtags']);
        $this->assertSame($product->affiliate_url, $c->fresh()->currentVersion->snapshot['metadata']['youtube']['affiliate_url']);
    }

    public function test_content_listing_accepts_absent_and_empty_search_parameters(): void
    {
        Content::create(['title' => 'ElevenLabs demo', 'created_by' => $this->admin->id, 'locks' => [], 'status' => 'GENERATING']);
        foreach (['', '?page=1', '?search=', '?search=&page=1', '?search=elevenlabs&page=1'] as $query) {
            $this->getJson($this->root.'/contents'.$query)->assertOk()->assertJsonPath('data.total', 1);
        }
    }

    public function test_content_schema_and_product_brand_relationship_are_validated(): void
    {
        $s = $this->snapshot(); $s['scenes'][0]['duration'] = 50;
        $this->postJson($this->root.'/contents', ['title' => 'Bad master', 'snapshot' => $s])->assertUnprocessable();
        $brand = Brand::create(['name' => 'Brand', 'slug' => 'brand']);
        $product = AffiliateProduct::create(['name' => 'Tool', 'slug' => 'tool', 'brand_id' => $brand->id, 'affiliate_url' => 'https://example.test']);
        $this->postJson($this->root.'/contents', ['title' => 'Wrong brand', 'affiliate_product_id' => $product->id, 'snapshot' => $this->snapshot()])->assertUnprocessable();
    }

    public function test_metadata_revision_reuses_voice_and_video_and_returns_for_review(): void
    {
        $this->qa(); $c = $this->content(); $base = $c->currentVersion;
        $this->mock(VoiceAgent::class, fn ($m) => $m->shouldNotReceive('generate'));
        $this->mock(VideoComposerAgent::class, fn ($m) => $m->shouldNotReceive('render'));
        $meta = $base->snapshot['metadata']; $meta['youtube']['title'] = 'New tutorial title';
        $id = $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $base->id, 'feedback' => 'Use a clearer YouTube title.', 'target' => 'metadata.youtube', 'patch' => ['metadata' => $meta]])->assertAccepted()->json('data.id');
        $this->execute($id);
        $c->refresh(); $new = $c->currentVersion;
        $this->assertSame('REVIEW_PENDING', $c->status);
        $this->assertSame($base->artifacts, $new->artifacts);
        $this->assertSame(['qa'], $new->steps);
        $this->assertSame('New tutorial title', $new->snapshot['metadata']['youtube']['title']);
        $this->assertSame($this->snapshot(), $base->fresh()->snapshot);
        $this->assertSame('COMPLETED', Feedback::find($id)->status);
        $this->execute($id); $this->assertSame(2, $c->versions()->count());
        $this->getJson($this->root.'/contents/'.$c->id)->assertOk()->assertJsonPath('data.versions.0.number', 2)->assertJsonPath('data.versions.0.changes.0.component', 'metadata.youtube');
    }

    public function test_script_revision_regenerates_voice_video_and_captions(): void
    {
        $this->qa(); $c = $this->content();
        $voice = app(AssetStore::class)->put('new voice', 'wav', 'audio/wav', true);
        $video = app(AssetStore::class)->put('new video', 'mp4', 'video/mp4', true);
        $this->mock(VoiceAgent::class, fn ($m) => $m->shouldReceive('generate')->once()->andReturn($voice));
        $this->mock(VideoComposerAgent::class, fn ($m) => $m->shouldReceive('render')->once()->andReturn($video));
        $id = $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'Make the narration concise.', 'target' => 'script', 'patch' => ['script' => 'Generate your voice in a few clicks.']])->assertAccepted()->json('data.id');
        $this->execute($id);
        $v = $c->fresh()->currentVersion;
        $this->assertSame(['voice', 'render', 'qa'], $v->steps);
        $this->assertSame($v->snapshot['script'], $v->snapshot['captions']);
        $this->assertSame($voice, $v->artifacts['voice']);
    }

    public function test_caption_revision_rerenders_without_regenerating_voice(): void
    {
        $this->qa(); $c = $this->content();
        $this->mock(VoiceAgent::class, fn ($m) => $m->shouldNotReceive('generate'));
        $video = app(AssetStore::class)->put('new caption video', 'mp4', 'video/mp4', true);
        $this->mock(VideoComposerAgent::class, fn ($m) => $m->shouldReceive('render')->once()->andReturn($video));
        $id = $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'Shorten visible captions.', 'target' => 'captions', 'patch' => ['captions' => 'Create a voice from text.']])->assertAccepted()->json('data.id');
        $this->execute($id); $this->assertSame(['render', 'qa'], $c->fresh()->currentVersion->steps);
    }

    public function test_component_scene_and_downstream_locks_are_enforced(): void
    {
        $c = $this->content();
        $c->update(['locks' => ['script', 'scenes.intro', 'voice', 'video']]);
        $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'Change script', 'patch' => ['script' => 'Changed script']])->assertUnprocessable();
        $scenes = $c->currentVersion->snapshot['scenes']; $scenes[0]['text'] = 'Changed scene';
        $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'Change opening', 'patch' => ['scenes' => $scenes]])->assertUnprocessable();
        $c->update(['locks' => ['voice']]);
        $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'Change script', 'patch' => ['script' => 'Changed script']])->assertUnprocessable()->assertJsonValidationErrors('locks');
        $c->update(['locks' => ['video']]);
        $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'Change CTA', 'patch' => ['cta' => 'New CTA']])->assertUnprocessable();
        $c->update(['locks' => ['captions']]);
        $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'Change script', 'patch' => ['script' => 'Changed script']])->assertUnprocessable();
        $this->assertSame(1, $c->versions()->count());
    }

    public function test_provider_cannot_change_a_locked_or_out_of_scope_component(): void
    {
        $c = $this->content();
        $this->mock(AIProviderInterface::class, fn ($m) => $m->shouldReceive('revise')->andReturn(['script' => 'Malicious out of scope change']));
        $id = $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'Update only the CTA.', 'target' => 'cta'])->assertAccepted()->json('data.id');
        $this->execute($id);
        $this->assertSame('FAILED', Feedback::find($id)->status);
        $this->assertSame(1, $c->versions()->count());
        $this->assertSame('REVIEW_PENDING', $c->fresh()->status);
    }

    public function test_openai_feedback_uses_structured_patch_and_logs_usage(): void
    {
        $this->qa(); config(['affiliate_agent.provider' => 'openai', 'affiliate_agent.openai_key' => 'test-key']);
        $c = $this->content(); $meta = $c->currentVersion->snapshot['metadata']; $meta['pinterest']['caption'] = 'A more useful tutorial';
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['patch' => ['metadata' => $meta]])]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50]])]);
        $id = $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'Make the Pinterest caption more useful.', 'target' => 'metadata.pinterest'])->assertAccepted()->json('data.id');
        $this->execute($id);
        $this->assertDatabaseHas('agent_usage', ['content_id' => $c->id, 'provider' => 'openai', 'input_tokens' => 100, 'output_tokens' => 50]);
        $this->assertSame('A more useful tutorial', $c->fresh()->currentVersion->snapshot['metadata']['pinterest']['caption']);
    }

    public function test_approved_content_edit_invalidates_approval_and_cancels_pending_publish(): void
    {
        $this->qa(); $c = $this->content(); $base = $c->current_version_id;
        $this->postJson($this->root.'/contents/'.$c->id.'/approve', ['expected_version_id' => $base])->assertOk();
        $this->postJson($this->root.'/contents/'.$c->id.'/publications', ['expected_version_id' => $base, 'platform' => 'youtube', 'mode' => 'mock'])->assertCreated();
        $meta = $c->currentVersion->snapshot['metadata']; $meta['youtube']['title'] = 'Revised title';
        $id = $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $base, 'feedback' => 'Revise title', 'patch' => ['metadata' => $meta]])->assertAccepted()->json('data.id');
        $this->assertNull($c->fresh()->final_approved_version_id);
        $this->assertSame('CANCELLED', Publication::first()->status);
        $this->assertNotNull($c->approvals()->first()->invalidated_at);
        $this->postJson($this->root.'/contents/'.$c->id.'/approve', ['expected_version_id' => $base])->assertConflict();
        $this->execute($id);
        $this->postJson($this->root.'/contents/'.$c->id.'/approve', ['expected_version_id' => $base])->assertConflict();
        $this->postJson($this->root.'/contents/'.$c->id.'/publications', ['expected_version_id' => $base, 'platform' => 'youtube', 'mode' => 'mock'])->assertConflict();
    }

    public function test_restoring_creates_a_new_version_and_never_revives_approval(): void
    {
        $this->qa(); $c = $this->content(); $base = $c->currentVersion;
        $new = $base->snapshot; $new['metadata']['youtube']['title'] = 'Updated';
        app(ReviewService::class)->append($c, ['snapshot' => $new, 'artifacts' => $base->artifacts, 'steps' => ['qa'], 'changes' => [], 'qa' => $base->qa], $this->admin->id);
        app(ReviewService::class)->approve($c->id, $c->current_version_id, $this->admin->id);
        $this->postJson($this->root.'/contents/'.$c->id.'/restore', ['expected_version_id' => $c->current_version_id, 'source_version_id' => $base->id])->assertOk()->assertJsonPath('data.number', 3);
        $this->assertSame($base->snapshot, $c->fresh()->currentVersion->snapshot);
        $this->assertSame('REVIEW_PENDING', $c->fresh()->status);
        $this->assertNull($c->fresh()->final_approved_version_id);
        $this->assertSame(3, $c->versions()->count());
    }

    public function test_queue_publishing_checks_exact_approval_and_is_idempotent(): void
    {
        $c = $this->content(); app(ReviewService::class)->approve($c->id, $c->current_version_id, $this->admin->id);
        $body = ['expected_version_id' => $c->current_version_id, 'platform' => 'youtube', 'mode' => 'mock'];
        $id = $this->postJson($this->root.'/contents/'.$c->id.'/publications', $body)->assertCreated()->json('data.id');
        $this->postJson($this->root.'/contents/'.$c->id.'/publications', $body)->assertCreated()->assertJsonPath('data.id', $id);
        app(PublishingAgent::class)->publish($id); app(PublishingAgent::class)->publish($id);
        $this->assertSame(5, Publication::count()); // Approval reserves one independent record per platform.
        $this->assertSame('MOCK_PUBLISHED', Publication::find($id)->status);
        $this->assertNull(Publication::find($id)->published_at);
        $manual = app(PublishingAgent::class)->schedule($c->id, ['expected_version_id' => $c->current_version_id, 'platform' => 'pinterest', 'mode' => 'manual'], $this->admin->id);
        app(ReviewService::class)->locks($c->id, $c->current_version_id, ['voice']);
        app(PublishingAgent::class)->publish($manual->id);
        $this->assertSame('CANCELLED', $manual->fresh()->status);
    }

    public function test_manual_export_and_confirmation_require_current_approval(): void
    {
        $c = $this->content(); app(ReviewService::class)->approve($c->id, $c->current_version_id, $this->admin->id);
        $p = app(PublishingAgent::class)->schedule($c->id, ['expected_version_id' => $c->current_version_id, 'platform' => 'pinterest', 'mode' => 'manual'], $this->admin->id);
        app(PublishingAgent::class)->publish($p->id);
        $this->assertSame('EXPORT_READY', $p->fresh()->status);
        $this->getJson($this->root.'/contents/'.$c->id.'/export?version_id='.$c->current_version_id)->assertOk();
        $this->postJson($this->root.'/publications/'.$p->id.'/confirm', ['external_id' => 'https://example.test/published/1'])->assertOk()->assertJsonPath('data.status', 'PUBLISHED_MANUALLY');
        $this->postJson($this->root.'/publications/'.$p->id.'/confirm', ['external_id' => 'https://example.test/published/1'])->assertConflict();
    }

    public function test_version_payload_is_immutable_and_hash_and_asset_tampering_block_publication(): void
    {
        $c = $this->content(); $v = $c->currentVersion;
        try { $v->update(['snapshot' => []]); $this->fail('Version was mutated'); } catch (\LogicException $e) { $this->assertSame('Content versions are immutable.', $e->getMessage()); }
        DB::table('agent_versions')->where('id', $v->id)->update(['snapshot_hash' => str_repeat('0', 64)]);
        $this->postJson($this->root.'/contents/'.$c->id.'/approve', ['expected_version_id' => $v->id])->assertConflict();
        DB::table('agent_versions')->where('id', $v->id)->update(['snapshot_hash' => $v->snapshot_hash]);
        Storage::disk('local')->put($v->artifacts['video']['path'], 'tampered bytes');
        $this->postJson($this->root.'/contents/'.$c->id.'/approve', ['expected_version_id' => $v->id])->assertConflict();
    }

    public function test_invalid_qa_cannot_be_approved_and_rejection_is_audited(): void
    {
        $c = $this->content(false);
        $this->postJson($this->root.'/contents/'.$c->id.'/approve', ['expected_version_id' => $c->current_version_id])->assertUnprocessable();
        $this->postJson($this->root.'/contents/'.$c->id.'/reject', ['expected_version_id' => $c->current_version_id, 'reason' => 'The disclosure is unreadable.'])->assertOk()->assertJsonPath('data.status', 'REJECTED');
        $this->assertSame('REJECTED', $c->feedback()->first()->status);
    }

    public function test_signed_previews_require_valid_signature_and_version_is_bound(): void
    {
        $c = $this->content();
        $url = URL::temporarySignedRoute('agent.asset', now()->addMinute(), ['version' => $c->current_version_id, 'kind' => 'video']);
        $this->get($url)->assertOk();
        $this->get(str_replace('/video?', '/voice?', $url))->assertForbidden();
        $this->travel(2)->minutes(); $this->get($url)->assertForbidden();
    }

    public function test_preferences_are_explicit_scoped_configurable_and_do_not_mutate_content(): void
    {
        $c = $this->content(); $version = $c->current_version_id;
        $brand = Brand::create(['name' => 'Brand', 'slug' => 'brand']);
        $payload = ['scope' => 'brand', 'brand_id' => $brand->id, 'component' => 'script', 'instruction' => 'Use shorter hooks', 'enabled' => true];
        $id = $this->postJson($this->root.'/preferences', $payload)->assertCreated()->json('data.id');
        $this->assertSame([], app(PreferenceService::class)->applicable($c));
        $c->update(['brand_id' => $brand->id]);
        $this->assertCount(1, app(PreferenceService::class)->applicable($c));
        $this->putJson($this->root.'/preferences/'.$id, [...$payload, 'instruction' => 'Use concise educational hooks'])->assertOk();
        $this->deleteJson($this->root.'/preferences/'.$id)->assertOk();
        $this->assertSame([], app(PreferenceService::class)->applicable($c));
        $this->assertSame($version, $c->fresh()->current_version_id);
        $this->assertSame(1, $c->versions()->count());
    }

    public function test_agent_routes_require_active_admin_authentication(): void
    {
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'status' => true]);
        $this->admin->update(['role_id' => $role->id]);
        Sanctum::actingAs($this->admin->fresh());
        $this->getJson($this->root.'/contents')->assertForbidden();
        $this->postJson($this->root.'/preferences', [])->assertForbidden();
    }

    public function test_stale_feedback_and_parallel_revisions_cannot_overwrite_content(): void
    {
        $c = $this->content();
        $id = $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'set cta: New CTA'])->assertAccepted()->json('data.id');
        $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'set cta: Other CTA'])->assertConflict();
        $this->putJson($this->root.'/contents/'.$c->id.'/locks', ['expected_version_id' => $c->current_version_id, 'locks' => ['video']])->assertConflict();
        $c->update(['locks' => ['video']]); // Simulate out-of-band state drift.
        $this->execute($id); $this->assertSame('FAILED', Feedback::find($id)->status);
        $this->assertSame(1, $c->versions()->count());
    }

    public function test_revision_retry_reuses_the_finished_voice_after_a_render_failure(): void
    {
        $this->qa(); $c = $this->content();
        $voice = app(AssetStore::class)->put('retry voice', 'wav', 'audio/wav', true);
        $video = app(AssetStore::class)->put('retry video', 'mp4', 'video/mp4', true);
        $this->mock(VoiceAgent::class, fn ($m) => $m->shouldReceive('generate')->once()->andReturn($voice));
        $this->mock(VideoComposerAgent::class, fn ($m) => $m->shouldReceive('render')->twice()->andReturnUsing(function () use ($video) {
            static $tries = 0;
            if ($tries++ === 0) throw new \RuntimeException('Transient FFmpeg failure');
            return $video;
        }));
        $id = $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'Change the narration.', 'target' => 'script', 'patch' => ['script' => 'A better narrated tutorial.']])->assertAccepted()->json('data.id');
        try { $this->execute($id); $this->fail('First render should fail'); } catch (\RuntimeException $e) { $this->assertSame('Transient FFmpeg failure', $e->getMessage()); }
        $this->assertSame(['voice'], Feedback::findOrFail($id)->checkpoint['completed']);
        $this->execute($id);
        $this->assertSame('COMPLETED', Feedback::findOrFail($id)->status);
        $this->assertSame($voice, $c->fresh()->currentVersion->artifacts['voice']);
    }

    public function test_initial_generation_retry_reuses_voice_and_creates_one_version(): void
    {
        $this->qa(); $c = Content::create(['title' => 'Retry initial render', 'created_by' => $this->admin->id, 'locks' => [], 'status' => 'GENERATING']);
        $voice = app(AssetStore::class)->put('initial voice', 'wav', 'audio/wav', true);
        $video = app(AssetStore::class)->put('initial video', 'mp4', 'video/mp4', true);
        $this->mock(VoiceAgent::class, fn ($m) => $m->shouldReceive('generate')->once()->andReturn($voice));
        $this->mock(VideoComposerAgent::class, fn ($m) => $m->shouldReceive('render')->twice()->andReturnUsing(function () use ($video) {
            static $tries = 0;
            if ($tries++ === 0) throw new \RuntimeException('Transient FFmpeg failure');
            return $video;
        }));
        $job = new GenerateContentJob($c->id, $this->snapshot(), $this->admin->id);
        try { $job->handle(app(RevisionAgent::class), app(ReviewService::class)); $this->fail('First render should fail'); } catch (\RuntimeException $e) { $this->assertSame('Transient FFmpeg failure', $e->getMessage()); }
        $this->assertSame(['voice'], $c->fresh()->checkpoint['completed']);
        $job->handle(app(RevisionAgent::class), app(ReviewService::class));
        $job->handle(app(RevisionAgent::class), app(ReviewService::class));
        $this->assertSame(1, $c->versions()->count());
        $this->assertSame('REVIEW_PENDING', $c->fresh()->status);
        $this->assertNull($c->fresh()->checkpoint);
    }

    public function test_real_ffmpeg_pipeline_produces_vertical_h264_aac_and_cleans_temporary_files(): void
    {
        Queue::fake();
        $before = glob(storage_path('app/agent-tmp/*')) ?: [];
        $c = Content::create(['title' => 'Real render check', 'created_by' => $this->admin->id, 'locks' => [], 'status' => 'GENERATING']);
        (new GenerateContentJob($c->id, $this->snapshot(), $this->admin->id))->handle(app(RevisionAgent::class), app(ReviewService::class));
        $v = $c->fresh()->currentVersion;
        $this->assertTrue($v->qa['passed']);
        $this->assertTrue($v->mock);
        $this->assertSame('REVIEW_PENDING', $c->fresh()->status);
        $this->assertSame(['voice', 'render', 'qa'], $v->steps);
        $this->assertDatabaseHas('agent_usage', ['operation' => 'render', 'provider' => 'ffmpeg']);
        $this->assertSame($before, glob(storage_path('app/agent-tmp/*')) ?: []);
    }
}
