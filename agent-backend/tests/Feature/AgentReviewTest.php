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
use App\Models\Media;
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
use App\Services\AffiliateAgent\RevisionPlanner;
use App\Services\AffiliateAgent\SceneDesignAgent;
use App\Services\AffiliateAgent\MediaSelectionAgent;
use App\Services\AffiliateAgent\SceneMediaRevision;
use App\Services\AffiliateAgent\VideoComposerAgent;
use App\Services\AffiliateAgent\VoiceAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Process\Process;
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

    public function test_initial_generation_selects_matching_video_before_copy_and_render(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['scenes'][0] += ['purpose' => 'hook', 'visual_style' => 'technology', 'background_type' => 'media', 'headline' => 'Computer workflow'];
        $snapshot['scenes'][1] += ['purpose' => 'demo', 'visual_style' => 'product', 'background_type' => 'media', 'headline' => 'Try the tool'];
        Storage::disk('local')->put('media/computer.mp4', 'test video');
        $media = Media::create(['name' => 'Computer technology screen footage', 'file_name' => 'computer.mp4', 'disk' => 'local', 'path' => 'media/computer.mp4', 'mime_type' => 'video/mp4', 'folder' => 'affiliate-agent']);
        $id = $this->postJson($this->root.'/contents', ['title' => 'Computer workflow', 'snapshot' => $snapshot])->assertCreated()->json('data.id');
        $this->qa();
        $voice = app(AssetStore::class)->put('audio', 'wav', 'audio/wav', true);
        $video = app(AssetStore::class)->put('render', 'mp4', 'video/mp4', true);
        $this->mock(VoiceAgent::class, fn ($m) => $m->shouldReceive('generate')->andReturn($voice));
        $this->mock(VideoComposerAgent::class, fn ($m) => $m->shouldReceive('render')->andReturn($video));
        (new GenerateContentJob($id, $snapshot, $this->admin->id))->handle(app(RevisionAgent::class), app(ReviewService::class));
        $version = Content::findOrFail($id)->currentVersion;
        $this->assertSame($media->id, $version->snapshot['scenes'][0]['media_id']);
        $this->assertSame('video/mp4', $version->artifacts['scenes']['intro']['mime_type']);
        $this->assertNull($version->snapshot['scenes'][1]['media_id']);
        $this->assertSame('animated_abstract', $version->snapshot['scenes'][1]['background_type']);
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
        $snapshot = $this->snapshot();
        $snapshot['scenes'] = app(SceneDesignAgent::class)->decorate($snapshot['scenes'], $c->id, 'Technology product');
        $snapshot['scenes'][0]['headline'] = 'Turn text into a voice';
        $snapshot['scenes'][0]['body'] = 'A clear product demonstration';
        $snapshot['scenes'][0]['template'] = 'link_in_bio';
        (new GenerateContentJob($c->id, $snapshot, $this->admin->id))->handle(app(RevisionAgent::class), app(ReviewService::class));
        $v = $c->fresh()->currentVersion;
        $this->assertTrue($v->qa['passed']);
        $this->assertTrue($v->mock);
        $this->assertSame('REVIEW_PENDING', $c->fresh()->status);
        $this->assertSame(['voice', 'render', 'qa'], $v->steps);
        $this->assertDatabaseHas('agent_usage', ['operation' => 'render', 'provider' => 'ffmpeg']);
        $this->assertSame($before, glob(storage_path('app/agent-tmp/*')) ?: []);
    }

    public function test_scene_design_metadata_templates_and_render_only_revision_are_preserved(): void
    {
        $original = $this->snapshot();
        $scenes = app(SceneDesignAgent::class)->decorate($original['scenes'], 5, 'Technology product');
        $this->assertSame('animated_abstract', $scenes[0]['background_type']);
        $this->assertSame('hook', $scenes[0]['purpose']);
        $this->assertSame('cta_button', $scenes[1]['template']);
        $scenes[0]['headline'] = 'A clear product headline';
        $scenes[0]['body'] = 'A smaller supporting line';
        $scenes[0]['template'] = 'link_in_bio';
        $scenes[0]['animation'] = 'slide_in';
        $revised = app(ContentSchema::class)->validate([...$original, 'scenes' => $scenes]);
        $this->assertSame('A clear product headline', $revised['scenes'][0]['headline']);
        $plan = app(RevisionPlanner::class)->plan($original, $revised, []);
        $this->assertSame(['render', 'qa'], $plan['steps']);
        $this->assertSame('scenes.intro', $plan['changes'][0]['component']);
        $ass = (new \ReflectionMethod(VideoComposerAgent::class, 'subtitles'))->invoke(app(VideoComposerAgent::class), $revised, false);
        $this->assertStringContainsString('Style: Headline,DejaVu Sans,86', $ass);
        $this->assertStringContainsString('Style: Body,DejaVu Sans,48', $ass);
        $this->assertStringContainsString('Style: CTA,DejaVu Sans,50', $ass);
        $this->assertStringContainsString('A clear product headline', str_replace('\\N', ' ', $ass));
        $this->assertStringContainsString('A smaller supporting line', str_replace('\\N', ' ', $ass));
        $this->assertStringContainsString('Link in bio', $ass);
        try { app(RevisionPlanner::class)->plan($original, $revised, ['video']); $this->fail('Locked video must block a scene render.'); }
        catch (\Illuminate\Validation\ValidationException) { /* Expected. */ }
    }

    public function test_invalid_provider_enums_are_normalized_without_changing_locked_components(): void
    {
        $this->qa(); $c = $this->content();
        $scenes = $c->currentVersion->snapshot['scenes'];
        $scenes[0] = app(SceneDesignAgent::class)->decorate([$scenes[0]], $c->id)[0];
        $scenes[0]['purpose'] = 'Introduce the product with a hook';
        $scenes[0]['animation'] = 'Headline fades up first';
        $scenes[0]['background_type'] = 'Animated audio waveform';
        $scenes[0]['headline'] = 'Voiceovers in seconds';
        $this->mock(AIProviderInterface::class, fn ($m) => $m->shouldReceive('revise')->once()->andReturn(['scenes' => $scenes]));
        $video = app(AssetStore::class)->put('new video', 'mp4', 'video/mp4', true);
        $this->mock(VideoComposerAgent::class, fn ($m) => $m->shouldReceive('render')->once()->andReturn($video));
        $id = $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'Use a stronger visual hook.', 'target' => 'scenes.intro'])->assertAccepted()->json('data.id');
        $this->execute($id);
        $scene = $c->fresh()->currentVersion->snapshot['scenes'][0];
        $this->assertSame('hook', $scene['purpose']);
        $this->assertSame('fade_up', $scene['animation']);
        $this->assertSame('animated_abstract', $scene['background_type']);
        $this->assertSame('COMPLETED', Feedback::findOrFail($id)->status);
    }

    public function test_unrecoverable_render_failure_resets_review_and_failed_revisions_do_not_use_daily_allowance(): void
    {
        $c = $this->content();
        config(['affiliate_agent.max_daily_revisions' => 1, 'app.debug' => true]);
        $id = $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'Update captions', 'patch' => ['captions' => 'Better captions']])->assertAccepted()->json('data.id');
        $this->mock(VideoComposerAgent::class, fn ($m) => $m->shouldReceive('render')->once()->andThrow(new \RuntimeException('FFmpeg unavailable')));
        try { $this->execute($id); $this->fail('Render should fail.'); } catch (\RuntimeException $e) { $this->assertSame('FFmpeg unavailable', $e->getMessage()); }
        (new ReviseContentJob($id))->failed(new \RuntimeException('FFmpeg unavailable'));
        $this->assertSame('FAILED', Feedback::findOrFail($id)->status);
        $this->assertStringContainsString('FFmpeg unavailable', Feedback::findOrFail($id)->error);
        $this->assertSame('REVIEW_PENDING', $c->fresh()->status);
        $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'Another update', 'patch' => ['captions' => 'Another caption']])->assertAccepted();
    }

    public function test_revision_lock_contention_leaves_queued_feedback_untouched_until_retry(): void
    {
        $this->qa(); $c = $this->content();
        $id = $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'set metadata.youtube.title: Better title'])->assertAccepted()->json('data.id');
        $lock = Cache::lock('agent-feedback:'.$id, 900); $this->assertTrue($lock->get());
        try {
            $this->execute($id);
            $this->assertSame('QUEUED', Feedback::findOrFail($id)->status);
            $this->assertSame('REVISION_PENDING', $c->fresh()->status);
        } finally { $lock->release(); }
        $this->execute($id);
        $this->assertSame('COMPLETED', Feedback::findOrFail($id)->status);
    }

    public function test_real_revision_expands_scene_timing_and_serves_new_vertical_mp4(): void
    {
        $c = Content::create(['title' => 'Timing revision', 'created_by' => $this->admin->id, 'locks' => [], 'status' => 'GENERATING']);
        $tmp = sys_get_temp_dir().'/agent-voice-'.bin2hex(random_bytes(8)).'.wav';
        try {
            (new Process([config('affiliate_agent.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'sine=frequency=220:sample_rate=44100', '-t', '16', $tmp]))->mustRun();
            $voice = app(AssetStore::class)->put(file_get_contents($tmp), 'wav', 'audio/wav', true);
        } finally { @unlink($tmp); }
        $artifacts = ['voice' => $voice, 'video' => app(AssetStore::class)->put('base video', 'mp4', 'video/mp4', true), 'scenes' => []];
        $base = app(ReviewService::class)->append($c, ['snapshot' => $this->snapshot(), 'artifacts' => $artifacts, 'steps' => ['voice', 'render', 'qa'], 'changes' => [], 'qa' => ['passed' => true]], $this->admin->id);
        $id = $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $base->id, 'feedback' => 'Improve visible captions', 'patch' => ['captions' => 'The updated, readable caption.']])->assertAccepted()->json('data.id');
        $this->execute($id);
        $v = $c->fresh()->currentVersion;
        $this->assertSame('COMPLETED', Feedback::findOrFail($id)->status);
        $this->assertSame('REVIEW_PENDING', $c->fresh()->status);
        $this->assertSame(2, $c->versions()->count());
        $this->assertGreaterThan(16, array_sum(array_column($v->snapshot['scenes'], 'duration')));
        $this->assertGreaterThan($v->snapshot['scenes'][1]['duration'], $v->snapshot['scenes'][0]['duration']);
        $this->assertTrue($v->qa['passed']);
        $this->assertTrue(app(AssetStore::class)->verify($v->artifacts['video']));
        $this->assertNotSame($base->artifacts['video']['sha256'], $v->artifacts['video']['sha256']);
        $versions = $this->getJson($this->root.'/contents/'.$c->id)->assertOk()->json('data.versions');
        $url = collect($versions)->firstWhere('id', $v->id)['assets']['video']['url'];
        $this->get($url)->assertOk()->assertHeader('content-type', 'video/mp4');
    }

    public function test_narration_over_45_seconds_fails_clearly_and_ffmpeg_path_supports_both_os_formats(): void
    {
        $composer = app(VideoComposerAgent::class);
        $path = new \ReflectionMethod(VideoComposerAgent::class, 'ffmpegFilterPath');
        $this->assertSame('/tmp/agent/captions.ass', $path->invoke($composer, '/tmp/agent/captions.ass'));
        $this->assertSame('E\\\\:/server/htdocs/agent/captions.ass', $path->invoke($composer, 'E:\\server\\htdocs\\agent\\captions.ass'));
        $c = $this->content(); $snapshot = $c->currentVersion->snapshot;
        $tmp = sys_get_temp_dir().'/agent-long-'.bin2hex(random_bytes(8)).'.wav';
        try {
            (new Process([config('affiliate_agent.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'sine=frequency=220:sample_rate=44100', '-t', '46', $tmp]))->mustRun();
            $artifacts = $c->currentVersion->artifacts;
            $artifacts['voice'] = app(AssetStore::class)->put(file_get_contents($tmp), 'wav', 'audio/wav', true);
            $this->expectExceptionMessage('Narration exceeds maximum 45-second video duration. Please shorten spoken script.');
            $composer->render($snapshot, $artifacts, $c->id);
        } finally { @unlink($tmp); }
    }

    public function test_scene_animations_typography_contrast_and_cta_timing_are_structured(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['scenes'] = app(SceneDesignAgent::class)->decorate($snapshot['scenes'], 4);
        $this->assertNotSame($snapshot['scenes'][0]['animation'], $snapshot['scenes'][1]['animation']);
        $snapshot['scenes'][0]['animation'] = 'cursor_reveal';
        $snapshot['scenes'][0]['headline'] = 'Create natural AI speech';
        $snapshot['scenes'][0]['body'] = 'Readable supporting line';
        $snapshot['scenes'][0]['background_type'] = 'solid_light';
        $snapshot['scenes'][1]['animation'] = 'slide_right';
        $snapshot['scenes'][1]['template'] = 'cta_button';
        $ass = (new \ReflectionMethod(VideoComposerAgent::class, 'subtitles'))->invoke(app(VideoComposerAgent::class), app(ContentSchema::class)->validate($snapshot), false);
        $this->assertStringContainsString('\\c&H002A2521&', $ass);
        $this->assertStringContainsString('Create natural AI speech', str_replace(['\\N', '_'], [' ', ''], $ass));
        $this->assertMatchesRegularExpression('/Dialogue: 0,[^\n]+Create natural AI[^\n]*_/', $ass);
        $this->assertStringContainsString('\\fs', $ass);
        $this->assertStringContainsString('\\pos(540,1590)', $ass);
        $this->assertStringContainsString('\\move(', $ass);
        $short = $snapshot; $long = $snapshot;
        $short['scenes'][0]['animation'] = 'fade_up'; $short['scenes'][0]['headline'] = 'Quick idea';
        $long['scenes'][0]['animation'] = 'fade_up'; $long['scenes'][0]['headline'] = str_repeat('Helpful product details ', 4);
        $shortAss = (new \ReflectionMethod(VideoComposerAgent::class, 'subtitles'))->invoke(app(VideoComposerAgent::class), $short, false);
        $longAss = (new \ReflectionMethod(VideoComposerAgent::class, 'subtitles'))->invoke(app(VideoComposerAgent::class), $long, false);
        preg_match('/Dialogue:[^\n]+Headline[^\n]*\\\\fs(\d+)/', $shortAss, $shortSize);
        preg_match('/Dialogue:[^\n]+Headline[^\n]*\\\\fs(\d+)/', $longAss, $longSize);
        $this->assertGreaterThan((int) $longSize[1], (int) $shortSize[1]);
        $snapshot['scenes'][0]['animation'] = 'not-an-animation';
        try { app(ContentSchema::class)->validate($snapshot); $this->fail('Invalid animation must fail.'); }
        catch (\Illuminate\Validation\ValidationException) { /* Expected. */ }
    }

    public function test_media_selection_prefers_relevant_assets_preserves_manual_choices_and_falls_back(): void
    {
        Storage::disk('local')->put('media/forest.mp4', 'forest video');
        Storage::disk('local')->put('media/screen.png', 'screen image');
        $forest = Media::create(['name' => 'Forest nature outdoor footage', 'file_name' => 'forest.mp4', 'disk' => 'local', 'path' => 'media/forest.mp4', 'mime_type' => 'video/mp4', 'folder' => 'affiliate-agent']);
        $screen = Media::create(['name' => 'Tool demo interface screenshot', 'file_name' => 'screen.png', 'disk' => 'local', 'path' => 'media/screen.png', 'mime_type' => 'image/png', 'folder' => 'affiliate-agent']);
        $scenes = [
            ['id' => 'forest', 'purpose' => 'benefit', 'text' => 'Explore the forest', 'headline' => 'Nature', 'visual_style' => 'nature', 'media_id' => null],
            ['id' => 'demo', 'purpose' => 'demo', 'text' => 'Open the tool interface', 'visual_style' => 'product', 'media_id' => null],
            ['id' => 'manual', 'purpose' => 'hook', 'text' => 'Keep this choice', 'visual_style' => 'nature', 'media_id' => $screen->id],
        ];
        $chosen = app(MediaSelectionAgent::class)->select($scenes, 'Tool');
        $this->assertSame($forest->id, $chosen[0]['media_id']);
        $this->assertSame($screen->id, $chosen[1]['media_id']);
        $this->assertSame($screen->id, $chosen[2]['media_id']);
        Storage::disk('local')->delete(['media/forest.mp4', 'media/screen.png']);
        $fallback = app(MediaSelectionAgent::class)->select([$scenes[0]], 'Tool');
        $this->assertNull($fallback[0]['media_id']);
        $this->assertSame('animated_abstract', $fallback[0]['background_type']);
    }

    public function test_media_selection_prefers_matching_video_over_newer_image_and_rejects_unrelated_video(): void
    {
        foreach (['forest.mp4', 'forest.png', 'computer.mp4', 'unrelated.mp4'] as $file) Storage::disk('local')->put('media/'.$file, 'asset');
        $forest = Media::create(['name' => 'Forest nature outdoor footage', 'file_name' => 'forest.mp4', 'disk' => 'local', 'path' => 'media/forest.mp4', 'mime_type' => 'video/mp4', 'folder' => 'affiliate-agent']);
        Media::create(['name' => 'Forest nature outdoor still', 'file_name' => 'forest.png', 'disk' => 'local', 'path' => 'media/forest.png', 'mime_type' => 'image/png', 'folder' => 'affiliate-agent']);
        $computer = Media::create(['name' => 'Computer technology screen footage', 'file_name' => 'computer.mp4', 'disk' => 'local', 'path' => 'media/computer.mp4', 'mime_type' => 'video/mp4', 'folder' => 'affiliate-agent']);
        Media::create(['name' => 'Unrelated sports video', 'file_name' => 'unrelated.mp4', 'disk' => 'local', 'path' => 'media/unrelated.mp4', 'mime_type' => 'video/mp4', 'folder' => 'affiliate-agent']);
        $scenes = [
            ['id' => 'nature', 'purpose' => 'benefit', 'text' => 'Enjoy a walk outside', 'headline' => 'Nature awaits', 'visual_style' => 'nature', 'background_type' => 'media', 'media_id' => null],
            ['id' => 'technology', 'purpose' => 'hook', 'text' => 'Work on your computer', 'headline' => 'Digital workflow', 'visual_style' => 'technology', 'background_type' => 'media', 'media_id' => null],
            ['id' => 'manual', 'purpose' => 'demo', 'text' => 'Keep selected video', 'visual_style' => 'product', 'background_type' => 'media', 'media_id' => $forest->id],
            ['id' => 'fallback', 'purpose' => 'benefit', 'text' => 'Fresh cooking recipes', 'visual_style' => 'minimal', 'background_type' => 'media', 'media_id' => null],
        ];
        $chosen = app(MediaSelectionAgent::class)->select($scenes);
        $this->assertSame($forest->id, $chosen[0]['media_id']);
        $this->assertSame($computer->id, $chosen[1]['media_id']);
        $this->assertSame($forest->id, $chosen[2]['media_id']);
        $this->assertNull($chosen[3]['media_id']);
        $this->assertSame('animated_abstract', $chosen[3]['background_type']);
        $scenes[0]['background_type'] = 'animated_abstract';
        $this->assertSame('animated_abstract', app(MediaSelectionAgent::class)->select([$scenes[0]])[0]['background_type']);
    }

    public function test_hosting_category_selects_relevant_cloud_video_without_reusing_unrelated_footage(): void
    {
        Storage::disk('local')->put('media/cloud.mp4', 'cloud footage');
        Storage::disk('local')->put('media/forest.mp4', 'forest footage');
        $cloud = Media::create(['name' => 'Cloud datacenter server video', 'file_name' => 'cloud.mp4', 'disk' => 'local', 'path' => 'media/cloud.mp4', 'mime_type' => 'video/mp4', 'folder' => 'affiliate-agent']);
        Media::create(['name' => 'Forest walking video', 'file_name' => 'forest.mp4', 'disk' => 'local', 'path' => 'media/forest.mp4', 'mime_type' => 'video/mp4', 'folder' => 'affiliate-agent']);
        $scenes = [['id' => 'hosting', 'text' => 'Launch your site', 'headline' => 'Go online', 'purpose' => 'benefit', 'visual_style' => 'technology', 'background_type' => 'media', 'media_id' => null]];
        $selected = app(MediaSelectionAgent::class)->select($scenes, 'Web plan', '', false, 'Web Hosting');
        $this->assertSame($cloud->id, $selected[0]['media_id']);
        $this->assertSame('video', $selected[0]['media_type']);
    }

    public function test_mobile_typography_keeps_headlines_dominant_and_bounded(): void
    {
        $composer = app(VideoComposerAgent::class);
        $short = $composer->typographyForScene(['headline' => 'Make it happen', 'body' => 'A useful supporting line']);
        $long = $composer->typographyForScene(['headline' => str_repeat('A longer product headline ', 5), 'body' => str_repeat('Clearly readable product details ', 5)]);
        $this->assertSame(90, $short['headline_size']);
        $this->assertSame(48, $short['body_size']);
        $this->assertSame(78, $long['headline_size']);
        $this->assertSame(42, $long['body_size']);
        foreach ([$short, $long] as $type) {
            $this->assertGreaterThan($type['body_size'], $type['headline_size']);
            $this->assertLessThanOrEqual(3, count(explode("\n", $type['wrapped_headline'])));
            foreach (explode("\n", $type['wrapped_headline']) as $line) $this->assertLessThanOrEqual($type['headline_line_width'], mb_strlen($line));
            foreach (explode("\n", $type['body']) as $line) $this->assertLessThanOrEqual(32, mb_strlen($line));
        }
    }

    public function test_uploaded_video_and_existing_image_create_immutable_media_versions_and_restore(): void
    {
        $this->qa(); $content = $this->content(); $original = $content->currentVersion;
        $upload = $this->post($this->root.'/media', ['name' => 'Hook product recording', 'file' => UploadedFile::fake()->create('hook.mp4', 8, 'video/mp4')], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertSame('video/mp4', $upload['mime_type']);
        $this->get($this->root.'/media/'.$upload['id'].'/preview')->assertOk()->assertHeader('Content-Type', 'video/mp4');
        Storage::disk('local')->put('media/demo.png', 'demo screenshot');
        $image = Media::create(['name' => 'Product demo interface', 'file_name' => 'demo.png', 'disk' => 'local', 'path' => 'media/demo.png', 'mime_type' => 'image/png', 'folder' => 'affiliate-agent']);
        $scenes = $original->snapshot['scenes'];
        $scenes[0] += ['media_mode' => 'manual', 'media_locked' => true, 'media_type' => 'video', 'media_fit' => 'cover', 'media_focus' => 'top', 'overlay_mode' => 'auto', 'media_origin' => 'uploaded', 'background_type' => 'media'];
        $scenes[0]['media_id'] = $upload['id'];
        $scenes[1] += ['media_mode' => 'manual', 'media_locked' => false, 'media_type' => 'image', 'media_fit' => 'contain', 'media_focus' => 'right', 'overlay_mode' => 'medium', 'media_origin' => 'manual', 'background_type' => 'media'];
        $scenes[1]['media_id'] = $image->id;
        $video = app(AssetStore::class)->put('rendered revision', 'mp4', 'video/mp4', true);
        $this->mock(VoiceAgent::class, fn ($m) => $m->shouldNotReceive('generate'));
        $this->mock(VideoComposerAgent::class, fn ($m) => $m->shouldReceive('render')->once()->andReturn($video));
        $id = $this->postJson($this->root.'/contents/'.$content->id.'/revisions', ['expected_version_id' => $original->id, 'feedback' => 'Admin selected a hook video and demo image.', 'target' => 'scenes', 'patch' => ['scenes' => $scenes]])->assertAccepted()->json('data.id');
        $this->execute($id);
        $version = $content->fresh()->currentVersion;
        $this->assertSame($upload['id'], $version->snapshot['scenes'][0]['media_id']);
        $this->assertSame('uploaded', $version->snapshot['scenes'][0]['media_origin']);
        $this->assertSame('video/mp4', $version->artifacts['scenes']['intro']['mime_type']);
        $this->assertSame($image->id, $version->snapshot['scenes'][1]['media_id']);
        $this->assertSame('image/png', $version->artifacts['scenes']['demo']['mime_type']);
        $this->assertSame('manual', $version->snapshot['scenes'][1]['media_origin']);
        $this->assertSame($original->snapshot, $original->fresh()->snapshot);
        $this->assertSame('scenes.intro', $version->changes[0]['component']);
        $this->assertNull($version->changes[0]['before']['media_id']);
        $this->assertSame($upload['id'], $version->changes[0]['after']['media_id']);

        $meta = $version->snapshot['metadata']; $meta['youtube']['title'] = 'Updated title';
        $id = $this->postJson($this->root.'/contents/'.$content->id.'/revisions', ['expected_version_id' => $version->id, 'feedback' => 'Update YouTube title only.', 'target' => 'metadata.youtube', 'patch' => ['metadata' => $meta]])->assertAccepted()->json('data.id');
        $this->execute($id);
        $this->assertSame($version->snapshot['scenes'], $content->fresh()->currentVersion->snapshot['scenes']);

        $blocked = $content->fresh()->currentVersion->snapshot['scenes']; $blocked[0]['media_id'] = $image->id;
        $this->postJson($this->root.'/contents/'.$content->id.'/revisions', ['expected_version_id' => $content->fresh()->current_version_id, 'feedback' => 'Replace locked hook media.', 'target' => 'scenes', 'patch' => ['scenes' => $blocked]])->assertUnprocessable();
        $this->postJson($this->root.'/contents/'.$content->id.'/restore', ['expected_version_id' => $content->fresh()->current_version_id, 'source_version_id' => $version->id])->assertOk();
        $this->assertSame($version->snapshot['scenes'], $content->fresh()->currentVersion->snapshot['scenes']);
        $this->assertSame($version->id, $content->fresh()->currentVersion->restored_from_id);
    }

    public function test_natural_media_feedback_selects_relevant_video_and_falls_back_without_a_match(): void
    {
        $this->qa(); $content = $this->content();
        Storage::disk('local')->put('media/forest.mp4', 'nature footage');
        $forest = Media::create(['name' => 'Nature forest outdoor walk footage', 'file_name' => 'forest.mp4', 'disk' => 'local', 'path' => 'media/forest.mp4', 'mime_type' => 'video/mp4', 'folder' => 'affiliate-agent']);
        $video = app(AssetStore::class)->put('rendered', 'mp4', 'video/mp4', true);
        $this->mock(VoiceAgent::class, fn ($m) => $m->shouldNotReceive('generate'));
        $this->mock(VideoComposerAgent::class, fn ($m) => $m->shouldReceive('render')->twice()->andReturn($video));
        $id = $this->postJson($this->root.'/contents/'.$content->id.'/revisions', ['expected_version_id' => $content->current_version_id, 'feedback' => 'Use a nature video for scene 2.', 'target' => 'scenes.demo'])->assertAccepted()->json('data.id');
        $this->execute($id);
        $scene = $content->fresh()->currentVersion->snapshot['scenes'][1];
        $this->assertSame($forest->id, $scene['media_id']);
        $this->assertSame('auto', $scene['media_mode']);
        $this->assertSame('video', $scene['media_type']);
        $this->assertSame('auto', $scene['media_origin']);
        $id = $this->postJson($this->root.'/contents/'.$content->id.'/revisions', ['expected_version_id' => $content->fresh()->current_version_id, 'feedback' => 'Replace scene 1 background with a technology video.', 'target' => 'scenes.intro'])->assertAccepted()->json('data.id');
        $this->execute($id);
        $first = $content->fresh()->currentVersion->snapshot['scenes'][0];
        $this->assertNull($first['media_id']);
        $this->assertSame('fallback', $first['media_origin']);
        $this->assertSame('animated_abstract', $first['background_type']);
        $this->assertSame($forest->id, $content->fresh()->currentVersion->snapshot['scenes'][1]['media_id']);
    }

    public function test_provider_patch_cannot_silently_replace_locked_manual_media(): void
    {
        $this->qa(); $content = $this->content();
        Storage::disk('local')->put('media/manual.png', 'selected image');
        $media = Media::create(['name' => 'Manual product image', 'file_name' => 'manual.png', 'disk' => 'local', 'path' => 'media/manual.png', 'mime_type' => 'image/png', 'folder' => 'affiliate-agent']);
        $snapshot = $content->currentVersion->snapshot;
        $snapshot['scenes'][0] = [...$snapshot['scenes'][0], 'media_id' => $media->id, 'media_mode' => 'manual', 'media_locked' => true, 'media_type' => 'image', 'media_origin' => 'manual', 'background_type' => 'media'];
        $artifacts = $content->currentVersion->artifacts;
        $artifacts['scenes']['intro'] = app(AssetStore::class)->copyMedia($media->id);
        $base = app(ReviewService::class)->append($content, ['snapshot' => $snapshot, 'artifacts' => $artifacts, 'changes' => [], 'steps' => ['render', 'qa'], 'qa' => ['passed' => true]], $this->admin->id);
        $tampered = $snapshot['scenes'];
        $tampered[0]['headline'] = 'A concise headline';
        $tampered[0]['media_id'] = null;
        $tampered[0]['background_type'] = 'animated_abstract';
        $this->mock(AIProviderInterface::class, fn ($m) => $m->shouldReceive('revise')->once()->andReturn(['scenes' => $tampered]));
        $this->mock(VoiceAgent::class, fn ($m) => $m->shouldNotReceive('generate'));
        $this->mock(VideoComposerAgent::class, fn ($m) => $m->shouldReceive('render')->once()->andReturn(app(AssetStore::class)->put('new video', 'mp4', 'video/mp4', true)));
        $id = $this->postJson($this->root.'/contents/'.$content->id.'/revisions', ['expected_version_id' => $base->id, 'feedback' => 'Make the opening headline concise.', 'target' => 'scenes.intro'])->assertAccepted()->json('data.id');
        $this->execute($id);
        $revised = $content->fresh()->currentVersion->snapshot['scenes'][0];
        $this->assertSame($media->id, $revised['media_id']);
        $this->assertSame('manual', $revised['media_mode']);
        $this->assertTrue($revised['media_locked']);
        $this->assertSame('A concise headline', $revised['headline']);
    }

    public function test_keep_and_do_not_change_media_feedback_are_explicit_locks(): void
    {
        $policy = app(SceneMediaRevision::class);
        $scenes = [
            ['id' => 'hook', 'media_id' => 42, 'media_mode' => 'manual', 'background_type' => 'media'],
            ['id' => 'closing', 'media_id' => null, 'media_mode' => 'auto', 'background_type' => 'animated_abstract'],
        ];
        $tampered = $scenes; $tampered[0]['media_id'] = null;
        $kept = $policy->apply($scenes, $tampered, 'Keep the current video in scene 1.', false);
        $this->assertSame(42, $kept[0]['media_id']);
        $this->assertTrue($kept[0]['media_locked']);
        $all = $policy->apply($scenes, $tampered, 'Do not change any background media.', false);
        $this->assertSame(42, $all[0]['media_id']);
        $this->assertTrue($all[0]['media_locked']);
        $this->assertTrue($all[1]['media_locked']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $policy->apply($scenes, $scenes, 'Use the uploaded video as the background for the hook.', false);
    }

    public function test_media_fit_focus_overlay_and_cross_platform_ffmpeg_paths(): void
    {
        $composer = app(VideoComposerAgent::class);
        $motion = new \ReflectionMethod(VideoComposerAgent::class, 'backgroundMotion');
        $this->assertStringContainsString('crop=1080:1920', $motion->invoke($composer, ['media_fit' => 'cover', 'media_focus' => 'top'], false, true));
        $this->assertStringContainsString("y='0'", $motion->invoke($composer, ['media_fit' => 'cover', 'media_focus' => 'top'], false, true));
        $this->assertStringNotContainsString('sin(', $motion->invoke($composer, ['media_fit' => 'cover', 'media_focus' => 'center'], false, true));
        $this->assertStringNotContainsString('cos(', $motion->invoke($composer, ['media_fit' => 'cover', 'media_focus' => 'center'], false, true));
        $this->assertStringContainsString('pad=1080:1920', $motion->invoke($composer, ['media_fit' => 'contain', 'media_focus' => 'right'], false, false));
        $this->assertStringContainsString('sin(n/60)', $motion->invoke($composer, ['media_fit' => 'cover', 'media_focus' => 'center'], false, false));
        $snapshot = app(ContentSchema::class)->validate($this->snapshot());
        $ass = (new \ReflectionMethod(VideoComposerAgent::class, 'subtitles'))->invoke($composer, $snapshot, false, ['intro' => true]);
        $this->assertStringContainsString('\\c&H002A2521&', $ass);
        $filterPath = new \ReflectionMethod(VideoComposerAgent::class, 'ffmpegFilterPath');
        $this->assertSame('captions.ass', $filterPath->invoke($composer, 'captions.ass'));
        $this->assertStringContainsString('C\\\\:', $filterPath->invoke($composer, 'C:\\temp\\captions.ass'));
        $this->assertStringContainsString('/tmp/captions.ass', $filterPath->invoke($composer, '/tmp/captions.ass'));
    }

    public function test_real_renderer_consumes_video_and_image_scene_assets(): void
    {
        $content = Content::create(['title' => 'Mixed media render', 'created_by' => $this->admin->id, 'locks' => [], 'status' => 'GENERATING']);
        $tmp = sys_get_temp_dir().'/agent-mixed-'.bin2hex(random_bytes(8));
        mkdir($tmp);
        try {
            (new Process([config('affiliate_agent.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'testsrc2=size=320x180:rate=15', '-t', '1', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', $tmp.'/clip.mp4']))->mustRun();
            (new Process([config('affiliate_agent.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'color=c=white:s=500x500', '-frames:v', '1', $tmp.'/still.png']))->mustRun();
            (new Process([config('affiliate_agent.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'sine=frequency=220:sample_rate=44100', '-t', '15', $tmp.'/voice.wav']))->mustRun();
            $video = app(AssetStore::class)->put(file_get_contents($tmp.'/clip.mp4'), 'mp4', 'video/mp4');
            $image = app(AssetStore::class)->put(file_get_contents($tmp.'/still.png'), 'png', 'image/png');
            $voice = app(AssetStore::class)->put(file_get_contents($tmp.'/voice.wav'), 'wav', 'audio/wav', true);
            $snapshot = $this->snapshot();
            $snapshot['scenes'] = app(SceneDesignAgent::class)->decorate($snapshot['scenes'], $content->id);
            $snapshot['scenes'][0] = [...$snapshot['scenes'][0], 'media_id' => 1, 'background_type' => 'media', 'media_mode' => 'manual', 'media_type' => 'video', 'media_fit' => 'cover', 'media_focus' => 'top', 'overlay_mode' => 'strong'];
            $snapshot['scenes'][1] = [...$snapshot['scenes'][1], 'media_id' => 2, 'background_type' => 'media', 'media_mode' => 'manual', 'media_type' => 'image', 'media_fit' => 'contain', 'media_focus' => 'right', 'overlay_mode' => 'none'];
            $snapshot['scenes'][0]['duration'] = 7.5; $snapshot['scenes'][1]['duration'] = 7.5;
            $render = app(VideoComposerAgent::class)->render($snapshot, ['voice' => $voice, 'scenes' => ['intro' => $video, 'demo' => $image]], $content->id, ['scenes']);
            $this->assertTrue(app(AssetStore::class)->verify($render));
            $probe = new Process([config('affiliate_agent.ffprobe'), '-v', 'error', '-show_entries', 'stream=codec_name,width,height', '-of', 'json', app(AssetStore::class)->absolute($render)]);
            $probe->mustRun();
            $streams = json_decode($probe->getOutput(), true)['streams'];
            $this->assertSame(1080, $streams[0]['width']);
            $this->assertSame(1920, $streams[0]['height']);
            $this->assertSame('h264', $streams[0]['codec_name']);
        } finally {
            foreach (glob($tmp.'/*') ?: [] as $file) @unlink($file);
            @rmdir($tmp);
        }
    }

    public function test_uploaded_image_video_and_auto_scene_complete_real_revision_and_mp4(): void
    {
        $tmp = sys_get_temp_dir().'/agent-upload-flow-'.bin2hex(random_bytes(8));
        mkdir($tmp);
        try {
            (new Process([config('affiliate_agent.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'color=c=skyblue:s=480x640', '-frames:v', '1', $tmp.'/hook.png']))->mustRun();
            (new Process([config('affiliate_agent.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'testsrc2=size=320x240:rate=15', '-t', '1', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', $tmp.'/demo.mp4']))->mustRun();
            (new Process([config('affiliate_agent.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'color=c=navy:s=480x640', '-frames:v', '1', $tmp.'/closing.png']))->mustRun();
            $spokenPreview = getenv('AFFILIATE_AGENT_SPOKEN_PREVIEW') === '1';
            if ($spokenPreview) {
                foreach (['See the product', 'Watch the workflow', 'Explore cloud hosting'] as $i => $spoken) {
                    (new Process([config('affiliate_agent.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'flite=text='.$spoken.':voice=slt', '-af', 'apad', '-t', '5', $tmp.'/spoken-'.$i.'.wav']))->mustRun();
                }
                (new Process([config('affiliate_agent.ffmpeg'), '-y', '-i', $tmp.'/spoken-0.wav', '-i', $tmp.'/spoken-1.wav', '-i', $tmp.'/spoken-2.wav', '-filter_complex', '[0:a][1:a][2:a]concat=n=3:v=0:a=1[a]', '-map', '[a]', $tmp.'/voice.wav']))->mustRun();
            } else (new Process([config('affiliate_agent.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'sine=frequency=220:sample_rate=44100', '-t', '15', $tmp.'/voice.wav']))->mustRun();
            $content = Content::create(['title' => 'Mixed upload review', 'created_by' => $this->admin->id, 'locks' => [], 'status' => 'GENERATING']);
            $snapshot = $this->snapshot();
            $snapshot['scenes'] = [
                ['id' => 'hook', 'text' => 'See the product', 'duration' => 5, 'media_id' => null, 'purpose' => 'hook', 'headline' => 'See the product', 'body' => 'A clear introduction', 'visual_style' => 'nature', 'background_type' => 'animated_abstract', 'animation' => 'cursor_reveal'],
                ['id' => 'demo', 'text' => 'Watch the workflow', 'duration' => 5, 'media_id' => null, 'purpose' => 'demo', 'headline' => 'Watch the workflow', 'body' => 'A practical example', 'visual_style' => 'product', 'background_type' => 'animated_abstract', 'animation' => 'slide_up'],
                ['id' => 'closing', 'text' => 'Explore cloud hosting', 'duration' => 5, 'media_id' => null, 'purpose' => 'closing', 'headline' => 'Explore cloud hosting', 'body' => 'Visit the product page', 'visual_style' => 'technology', 'background_type' => 'animated_abstract', 'animation' => 'scale_pop'],
            ];
            $snapshot['script'] = VoiceAgent::narration($snapshot);
            $snapshot['captions'] = $snapshot['script'];
            $voice = app(AssetStore::class)->put(file_get_contents($tmp.'/voice.wav'), 'wav', 'audio/wav', !$spokenPreview);
            $base = app(ReviewService::class)->append($content, ['snapshot' => $snapshot, 'artifacts' => ['voice' => $voice, 'video' => app(AssetStore::class)->put('initial placeholder', 'mp4', 'video/mp4', true), 'scenes' => []], 'steps' => ['voice', 'render', 'qa'], 'changes' => [], 'qa' => ['passed' => true]], $this->admin->id);
            $image = $this->post($this->root.'/media', ['name' => 'Uploaded hook image', 'file' => new UploadedFile($tmp.'/hook.png', 'hook.png', 'image/png', null, true)], ['Accept' => 'application/json'])->assertCreated()->json('data');
            $video = $this->post($this->root.'/media', ['name' => 'Uploaded product demo video', 'file' => new UploadedFile($tmp.'/demo.mp4', 'demo.mp4', 'video/mp4', null, true)], ['Accept' => 'application/json'])->assertCreated()->json('data');
            Storage::disk('local')->put('media/closing.png', file_get_contents($tmp.'/closing.png'));
            $automatic = Media::create(['name' => 'Cloud hosting technology server outro', 'file_name' => 'closing.png', 'disk' => 'local', 'path' => 'media/closing.png', 'mime_type' => 'image/png', 'folder' => 'affiliate-agent']);
            $this->get($this->root.'/media/'.$image['id'].'/preview')->assertOk()->assertHeader('Content-Type', 'image/png');
            $this->get($this->root.'/media/'.$video['id'].'/preview')->assertOk()->assertHeader('Content-Type', 'video/mp4');
            $scenes = $snapshot['scenes'];
            $scenes[0] = [...$scenes[0], 'media_id' => $image['id'], 'media_mode' => 'manual', 'media_locked' => true, 'media_type' => 'image', 'media_origin' => 'uploaded', 'media_fit' => 'cover', 'media_focus' => 'top', 'overlay_mode' => 'strong', 'background_type' => 'media'];
            $scenes[1] = [...$scenes[1], 'media_id' => $video['id'], 'media_mode' => 'manual', 'media_locked' => false, 'media_type' => 'video', 'media_origin' => 'uploaded', 'media_fit' => 'cover', 'media_focus' => 'center', 'overlay_mode' => 'auto', 'background_type' => 'media'];
            $scenes[2] = [...$scenes[2], 'media_mode' => 'auto', 'background_type' => 'media'];
            $id = $this->postJson($this->root.'/contents/'.$content->id.'/revisions', ['expected_version_id' => $base->id, 'feedback' => 'Use uploaded image and video; find a relevant closing background.', 'target' => 'scenes', 'patch' => ['scenes' => $scenes]])->assertAccepted()->json('data.id');
            $this->execute($id);
            $this->assertSame('COMPLETED', Feedback::findOrFail($id)->status);
            $version = $content->fresh()->currentVersion;
            $this->assertTrue($version->qa['passed']);
            $this->assertSame($image['id'], $version->snapshot['scenes'][0]['media_id']);
            $this->assertTrue($version->snapshot['scenes'][0]['media_locked']);
            $this->assertSame($video['id'], $version->snapshot['scenes'][1]['media_id']);
            $this->assertSame($automatic->id, $version->snapshot['scenes'][2]['media_id']);
            $this->assertSame('auto', $version->snapshot['scenes'][2]['media_origin']);
            $this->assertSame($snapshot['script'], $version->snapshot['script']);
            $this->assertSame($voice, $version->artifacts['voice']);
            $this->assertSame(['render', 'qa'], $version->steps);
            $file = app(AssetStore::class)->absolute($version->artifacts['video']);
            $probe = new Process([config('affiliate_agent.ffprobe'), '-v', 'error', '-show_entries', 'format=duration:stream=codec_name,width,height', '-of', 'json', $file]);
            $probe->mustRun();
            $data = json_decode($probe->getOutput(), true);
            $this->assertSame(1080, $data['streams'][0]['width']);
            $this->assertSame(1920, $data['streams'][0]['height']);
            $this->assertLessThanOrEqual(45, (float) $data['format']['duration']);
            if ($output = getenv('AFFILIATE_AGENT_PREVIEW_OUTPUT')) copy($file, $output);
        } finally {
            foreach (glob($tmp.'/*') ?: [] as $path) @unlink($path);
            @rmdir($tmp);
        }
    }

    public function test_voice_narration_uses_scene_order_and_script_edit_aligns_scene_lines(): void
    {
        $snapshot = $this->snapshot();
        $this->assertSame('Turn text into a voice Paste text and generate', VoiceAgent::narration($snapshot));
        $after = [...$snapshot, 'script' => 'First idea. Second explanation. Last action.'];
        $plan = app(RevisionPlanner::class)->plan($snapshot, $after, []);
        $this->assertSame($after['script'], VoiceAgent::narration($plan['snapshot']));
        $this->assertSame($after['script'], $plan['snapshot']['captions']);
        $this->assertSame(['voice', 'render', 'qa'], $plan['steps']);
    }

    public function test_visual_revision_reselects_only_changed_scene_and_preserves_other_media(): void
    {
        $this->qa(); $c = $this->content();
        Storage::disk('local')->put('media/tech.png', 'approved screenshot');
        $asset = Media::create(['name' => 'Technology screen product demo', 'file_name' => 'tech.png', 'disk' => 'local', 'path' => 'media/tech.png', 'mime_type' => 'image/png', 'folder' => 'affiliate-agent']);
        $scenes = $c->currentVersion->snapshot['scenes'];
        $scenes[0]['visual_style'] = 'technology';
        $scenes[0]['background_type'] = 'media';
        $video = app(AssetStore::class)->put('rendered', 'mp4', 'video/mp4', true);
        $this->mock(VideoComposerAgent::class, fn ($m) => $m->shouldReceive('render')->once()->andReturn($video));
        $id = $this->postJson($this->root.'/contents/'.$c->id.'/revisions', ['expected_version_id' => $c->current_version_id, 'feedback' => 'Use a technology screen visual.', 'target' => 'scenes.intro', 'patch' => ['scenes' => $scenes]])->assertAccepted()->json('data.id');
        $this->execute($id);
        $after = $c->fresh()->currentVersion->snapshot['scenes'];
        $this->assertSame($asset->id, $after[0]['media_id']);
        $this->assertNull($after[1]['media_id']);
        $this->assertSame('media', $after[0]['background_type']);
    }

    public function test_media_brightness_sampling_distinguishes_light_and_dark_backgrounds(): void
    {
        $paths = [];
        try {
            foreach (['black', 'white'] as $color) {
                $path = sys_get_temp_dir().'/agent-'.$color.'-'.bin2hex(random_bytes(6)).'.png';
                (new Process([config('affiliate_agent.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'color=c='.$color.':s=80x80', '-frames:v', '1', $path]))->mustRun();
                $paths[$color] = $path;
            }
            $method = new \ReflectionMethod(VideoComposerAgent::class, 'brightness');
            $this->assertLessThan(25, $method->invoke(app(VideoComposerAgent::class), $paths['black']));
            $this->assertGreaterThan(230, $method->invoke(app(VideoComposerAgent::class), $paths['white']));
        } finally { foreach ($paths as $path) @unlink($path); }
    }

    public function test_elevenlabs_receives_scene_narration_in_order_instead_of_visual_headlines(): void
    {
        $c = $this->content();
        config(['affiliate_agent.voice_provider' => 'elevenlabs', 'affiliate_agent.elevenlabs_key' => 'test-key', 'affiliate_agent.voice_id' => 'test-voice']);
        Http::fake(['api.elevenlabs.io/*' => Http::response('audio-fixture', 200, ['Content-Type' => 'audio/mpeg'])]);
        $snapshot = $c->currentVersion->snapshot;
        $snapshot['scenes'][0]['headline'] = 'Visual headline only';
        app(VoiceAgent::class)->generate($snapshot, $c->id);
        Http::assertSent(fn ($request) => $request['text'] === 'Turn text into a voice Paste text and generate' && !str_contains($request['text'], 'Visual headline only'));
    }

    public function test_media_limits_validate_image_video_size_and_extension(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l2cAAAAASUVORK5CYII=');
        $this->getJson($this->root.'/media-limits')->assertOk()->assertJsonPath('data.image', 10)->assertJsonPath('data.video', 100);
        $this->postJson($this->root.'/media', ['name' => 'Too large image', 'file' => UploadedFile::fake()->createWithContent('large.png', $png.str_repeat('x', 11 * 1024 * 1024))])->assertUnprocessable();
        $this->postJson($this->root.'/media', ['name' => 'Wrong extension', 'file' => UploadedFile::fake()->createWithContent('photo.gif', $png)])->assertUnprocessable();
        $this->postJson($this->root.'/media', ['name' => 'Valid image', 'file' => UploadedFile::fake()->createWithContent('photo.png', $png)])->assertCreated()->assertJsonPath('data.mime_type', 'image/png');
    }

    public function test_delete_content_hides_it_but_keeps_immutable_history_and_artifacts(): void
    {
        $content = $this->content();
        $version = $content->currentVersion;
        $artifact = $version->artifacts['video'];
        $this->deleteJson($this->root.'/contents/'.$content->id)->assertOk();
        $this->assertSoftDeleted('agent_contents', ['id' => $content->id]);
        $this->getJson($this->root.'/contents/'.$content->id)->assertNotFound();
        $this->assertDatabaseHas('agent_versions', ['id' => $version->id]);
        $this->assertTrue(app(AssetStore::class)->verify($artifact));
        $this->assertSame(0, $this->getJson($this->root.'/contents')->assertOk()->json('data.total'));
    }

    public function test_delete_rejects_content_with_active_generation_or_publication(): void
    {
        $content = Content::create(['title' => 'Generating', 'created_by' => $this->admin->id, 'locks' => [], 'status' => 'GENERATING']);
        $this->deleteJson($this->root.'/contents/'.$content->id)->assertConflict();
        $content = $this->content();
        $content->publications()->create(['version_id' => $content->current_version_id, 'snapshot_hash' => $content->currentVersion->snapshot_hash, 'platform' => 'youtube', 'mode' => 'manual', 'status' => 'SCHEDULED', 'scheduled_at' => now(), 'created_by' => $this->admin->id]);
        $this->deleteJson($this->root.'/contents/'.$content->id)->assertConflict();
    }
}
