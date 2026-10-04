<?php

namespace Tests\Feature;

use App\Jobs\AffiliateAgent\{GenerateContentJob,PlanDailyContentJob,SyncSourceJob};
use App\Models\{AffiliateProduct,Brand,Media,Role,User};
use App\Models\Agent\{Campaign,Content,Setting,SocialAccount,Source};
use App\Models\Agent\Publication;
use App\Models\Agent\PinterestDestination;
use App\Services\AffiliateAgent\{AssetStore,AutomationScheduler,ContentPlannerAgent,ContentSchema,GenerationWorkflow,PublishingAgent,ReviewService,SourceSyncService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentAutomationTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private string $root = '/api/v1/admin/affiliate-agent';

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'status' => true]);
        $this->admin = User::create(['name' => 'Admin', 'username' => 'admin-agent', 'email' => 'agent@example.test', 'password' => 'Password123!', 'role_id' => $role->id, 'status' => true]);
        Sanctum::actingAs($this->admin);
        Queue::fake();
    }

    public function test_manual_source_requires_approval_and_credentials_are_encrypted(): void
    {
        $brand = Brand::create(['name' => 'ElevenLabs', 'slug' => 'elevenlabs', 'status' => true]);
        $source = $this->postJson($this->root.'/sources', ['brand_id' => $brand->id, 'name' => 'Official notes', 'type' => 'MANUAL', 'notes' => 'Voice synthesis information from the official product documentation.', 'credentials' => 'secret-example'])->assertCreated()->json('data');
        $this->assertStringNotContainsString('secret-example', Source::find($source['id'])->getRawOriginal('credentials'));
        $this->getJson($this->root.'/sources')->assertOk()->assertDontSee('secret-example');
        $this->postJson($this->root.'/sources/'.$source['id'].'/sync')->assertAccepted();
        Queue::assertPushed(SyncSourceJob::class);
        app(SourceSyncService::class)->sync(Source::find($source['id']));
        $document = Source::find($source['id'])->documents()->first();
        $this->assertSame('PENDING', $document->status);
        $this->postJson($this->root.'/source-documents/'.$document->id.'/approve')->assertOk();
        $this->assertSame('APPROVED', $document->fresh()->status);
    }

    public function test_mix_deficit_and_daily_scheduler_queue_only_target_count(): void
    {
        $brand = Brand::create(['name' => 'Official', 'slug' => 'official', 'status' => true]);
        AffiliateProduct::create(['name' => 'Voice', 'slug' => 'voice', 'brand_id' => $brand->id, 'status' => true, 'affiliate_url' => 'https://example.test/link']);
        $settings = Setting::current();
        $this->putJson($this->root.'/settings', [...$settings->toArray(), 'mix' => ['tutorial' => 20, 'educational' => 20, 'use_case' => 20, 'tips' => 20, 'promotion' => 10]])->assertUnprocessable();
        for ($n = 0; $n < 3; $n++) Content::create(['title' => 'Promo '.$n, 'content_type' => 'promotion', 'status' => 'GENERATING', 'locks' => [], 'created_by' => $this->admin->id]);
        $this->assertNotSame('promotion', app(ContentPlannerAgent::class)->next($settings)['type']);
        $settings->update(['generation_enabled' => true, 'generation_time' => '00:00', 'generation_days' => [0,1,2,3,4,5,6]]);
        app(AutomationScheduler::class)->tick();
        Queue::assertPushed(PlanDailyContentJob::class, 2);
        app(AutomationScheduler::class)->tick();
        Queue::assertPushed(PlanDailyContentJob::class, 2);
    }

    public function test_active_campaign_is_prioritized_until_monthly_target(): void
    {
        $brand = Brand::create(['name' => 'Official', 'slug' => 'official-campaign', 'status' => true]);
        $product = AffiliateProduct::create(['name' => 'Voice', 'slug' => 'voice-campaign', 'brand_id' => $brand->id, 'status' => true, 'affiliate_url' => 'https://example.test/offer']);
        $campaign = Campaign::create(['brand_id' => $brand->id, 'affiliate_product_id' => $product->id, 'name' => 'Demo month', 'brief' => 'Show a verified voice demo.', 'priority' => 99, 'monthly_target' => 1, 'enabled' => true]);
        $plan = app(ContentPlannerAgent::class)->next(Setting::current());
        $this->assertSame($campaign->id, $plan['campaign']->id);
        Content::create(['title' => 'Demo month', 'content_type' => 'tutorial', 'campaign_id' => $campaign->id, 'created_by' => $this->admin->id, 'locks' => [], 'status' => 'GENERATING']);
        $this->assertNull(app(ContentPlannerAgent::class)->next(Setting::current())['campaign']);
    }

    public function test_approved_source_and_media_feed_an_actual_generation_job(): void
    {
        config(['affiliate_agent.provider' => 'mock']);
        $brand = Brand::create(['name' => 'Official', 'slug' => 'official-workflow', 'status' => true]);
        $product = AffiliateProduct::create(['name' => 'Voice', 'slug' => 'voice-workflow', 'brand_id' => $brand->id, 'status' => true, 'affiliate_url' => 'https://example.test/offer']);
        $source = Source::create(['brand_id' => $brand->id, 'name' => 'Official notes', 'type' => 'MANUAL', 'notes' => 'Verified voice synthesis feature.', 'enabled' => true]);
        app(SourceSyncService::class)->sync($source);
        app(SourceSyncService::class)->approve($source->documents()->first());
        Media::create(['user_id' => $this->admin->id, 'name' => 'Screenshot', 'file_name' => 'test.png', 'disk' => 'local', 'path' => 'test.png', 'mime_type' => 'image/png', 'size' => 3, 'folder' => 'affiliate-agent']);
        $content = app(GenerationWorkflow::class)->launch(Setting::current(), $this->admin->id);
        $this->assertSame($product->id, $content->affiliate_product_id);
        $this->assertSame('GENERATING', $content->status);
        Queue::assertPushed(GenerateContentJob::class, fn ($job) => $job->contentId === $content->id && $job->snapshot['scenes'][0]['media_id'] !== null);
    }

    public function test_oauth_state_exchanges_tokens_server_side_and_redacts_response(): void
    {
        config(['affiliate_agent.oauth.youtube.id' => 'client', 'affiliate_agent.oauth.youtube.secret' => 'secret']);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'private-token', 'refresh_token' => 'private-refresh', 'expires_in' => 3600]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'channel-1', 'snippet' => ['title' => 'Channel']]]]),
        ]);
        $url = $this->postJson($this->root.'/social-accounts/youtube/connect')->assertOk()->json('data.authorization_url');
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $this->assertStringContainsString('youtube.upload', $url);
        $this->get('/api/v1/affiliate-agent/oauth/youtube/callback?state='.$params['state'].'&code=valid')->assertRedirect();
        $account = SocialAccount::firstOrFail();
        $this->assertSame('private-token', $account->access_token);
        $this->assertStringNotContainsString('private-token', $account->getRawOriginal('access_token'));
        $this->getJson($this->root.'/social-accounts')->assertDontSee('private-token')->assertDontSee('private-refresh');
        $this->get('/api/v1/affiliate-agent/oauth/youtube/callback?state='.$params['state'].'&code=valid')->assertForbidden();
    }

    public function test_real_adapter_keeps_private_youtube_upload_distinct_from_public_success(): void
    {
        Storage::fake('local');
        config(['affiliate_agent.provider' => 'openai', 'affiliate_agent.voice_provider' => 'elevenlabs', 'affiliate_agent.openai_key' => 'configured', 'affiliate_agent.elevenlabs_key' => 'configured']);
        Setting::current()->update(['publishing_enabled' => true]);
        $account = SocialAccount::create(['platform' => 'youtube', 'external_id' => 'channel', 'name' => 'Channel', 'access_token' => 'encrypted-token', 'status' => 'CONNECTED', 'publishing_enabled' => true, 'api_review_status' => 'API_REVIEW_REQUIRED']);
        $metadata = [];
        foreach (ContentSchema::PLATFORMS as $platform) $metadata[$platform] = ['title' => 'Tutorial', 'caption' => 'Demo', 'description' => 'Official demo', 'hashtags' => ['#demo'], 'affiliate_url' => 'https://example.test/offer', 'cta' => 'Visit', 'disclosure' => 'Affiliate link'];
        $snapshot = ['script' => 'Demo', 'captions' => 'Demo', 'cta' => 'Visit', 'disclosure' => 'Affiliate link', 'voice' => ['voice_id' => null], 'scenes' => [['id' => 'intro', 'text' => 'Demo', 'duration' => 20, 'media_id' => null]], 'metadata' => $metadata];
        $content = Content::create(['title' => 'Demo', 'created_by' => $this->admin->id, 'locks' => [], 'status' => 'GENERATING']);
        $assets = ['voice' => app(AssetStore::class)->put('audio', 'wav', 'audio/wav'), 'video' => app(AssetStore::class)->put('video', 'mp4', 'video/mp4'), 'scenes' => []];
        app(ReviewService::class)->append($content, ['snapshot' => $snapshot, 'artifacts' => $assets, 'changes' => [], 'steps' => [], 'qa' => ['passed' => true]], $this->admin->id);
        app(ReviewService::class)->approve($content->id, $content->fresh()->current_version_id, $this->admin->id);
        $publication = Publication::where('content_id', $content->id)->where('platform', 'youtube')->firstOrFail();
        $publication->update(['mode' => 'api', 'social_account_id' => $account->id, 'scheduled_at' => now()->subMinute()]);
        Http::fake([
            'www.googleapis.com/upload/youtube/v3/videos*' => Http::response([], 200, ['Location' => 'https://www.googleapis.com/upload/session/123']),
            'www.googleapis.com/upload/session/123' => Http::response(['id' => 'video-123', 'status' => ['uploadStatus' => 'uploaded']]),
        ]);
        app(PublishingAgent::class)->publish($publication->id);
        $this->assertSame('API_REVIEW_REQUIRED', $publication->fresh()->status);
        $this->assertSame('video-123', $publication->fresh()->external_id);
        $this->assertNull($publication->fresh()->published_at);
        app(PublishingAgent::class)->publish($publication->id);
        Http::assertSentCount(2);
    }

    public function test_pinterest_board_and_optional_section_are_verified_and_stored_by_external_id(): void
    {
        $account = SocialAccount::create(['platform' => 'pinterest', 'external_id' => 'user-1', 'name' => 'Pinterest', 'access_token' => 'encrypted-token', 'status' => 'CONNECTED']);
        Http::fake([
            'api.pinterest.com/v5/boards/board-123/sections*' => Http::response(['items' => [['id' => 'section-456', 'name' => 'AI Voice & Audio']]]),
            'api.pinterest.com/v5/boards/board-123' => Http::response(['id' => 'board-123', 'name' => 'AI Tools']),
            'api.pinterest.com/v5/boards*' => Http::response(['items' => [['id' => 'board-123', 'name' => 'AI Tools']]]),
        ]);
        $base = $this->root.'/social-accounts/'.$account->id;
        $this->getJson($base.'/boards')->assertOk()->assertJsonPath('data.items.0.id', 'board-123');
        $this->getJson($base.'/boards/board-123/sections')->assertOk()->assertJsonPath('data.items.0.id', 'section-456');
        $this->putJson($base.'/destinations', ['scope_type' => 'account', 'external_board_id' => 'board-123'])->assertOk()->assertJsonPath('data.external_section_id', null);
        $this->putJson($base.'/destinations', ['scope_type' => 'account', 'external_board_id' => 'board-123', 'external_section_id' => 'section-456'])->assertOk()->assertJsonPath('data.external_section_name', 'AI Voice & Audio');
        $this->assertSame(1, PinterestDestination::count());
        $this->assertSame('board-123', PinterestDestination::first()->external_board_id);
        $this->assertSame('section-456', PinterestDestination::first()->external_section_id);
        $this->putJson($base.'/destinations', ['scope_type' => 'brand', 'scope_id' => 999, 'external_board_id' => 'board-123'])->assertUnprocessable();
    }

    public function test_setup_reports_missing_integrations_and_disconnecting_removes_tokens(): void
    {
        config(['affiliate_agent.openai_key' => null, 'affiliate_agent.elevenlabs_key' => null,
            'affiliate_agent.oauth.pinterest.id' => null, 'affiliate_agent.oauth.pinterest.secret' => null]);
        $this->getJson($this->root.'/setup')->assertOk()
            ->assertJsonPath('data.providers.openai.status', 'NOT_CONFIGURED')
            ->assertJsonPath('data.providers.elevenlabs.status', 'NOT_CONFIGURED')
            ->assertJsonPath('data.social_configured.pinterest', false);
        $this->getJson($this->root.'/tests/openai')->assertOk()->assertJsonPath('data.ok', false);
        $this->postJson($this->root.'/social-accounts/pinterest/connect')->assertStatus(409);
        $account = SocialAccount::create(['platform' => 'pinterest', 'external_id' => 'user-1', 'name' => 'Pinterest', 'access_token' => 'test-private-token', 'status' => 'CONNECTED', 'publishing_enabled' => true]);
        $this->deleteJson($this->root.'/social-accounts/'.$account->id)->assertOk()->assertJsonPath('data.status', 'AUTHORIZATION_REQUIRED');
        $this->assertNull($account->fresh()->access_token);
        $this->assertFalse($account->fresh()->publishing_enabled);
        $this->postJson($this->root.'/social-accounts/'.$account->id.'/test')->assertOk()->assertJsonPath('data.ok', false);
    }

    public function test_pinterest_migration_repairs_a_table_left_by_failed_mysql_index_creation(): void
    {
        Schema::table('agent_pinterest_destinations', fn (Blueprint $table) => $table->dropUnique('agent_pin_dest_scope_uq'));
        $this->assertFalse(Schema::hasIndex('agent_pinterest_destinations', 'agent_pin_dest_scope_uq'));
        $migration = require database_path('migrations/2026_10_04_000004_pinterest_destinations.php');
        $migration->up();
        $this->assertTrue(Schema::hasIndex('agent_pinterest_destinations', 'agent_pin_dest_scope_uq'));
        $migration->up();
    }
}
