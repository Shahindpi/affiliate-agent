<?php

namespace Tests\Feature;

use App\Jobs\AffiliateAgent\SyncSourceJob;
use App\Models\Agent\{Source, SourceDocument};
use App\Models\{Brand, Role, User};
use App\Services\AffiliateAgent\AutomationScheduler;
use App\Services\AffiliateAgent\SourceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SourceSyncLifecycleTest extends TestCase
{
    use RefreshDatabase;
    private string $root = '/api/v1/admin/affiliate-agent/sources';
    private string $url = 'https://elevenlabs.io/text-to-speech';

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'status' => true]);
        $admin = User::create(['name' => 'Admin', 'username' => 'source-admin', 'email' => 'source@example.test', 'password' => 'Password123!', 'role_id' => $role->id, 'status' => true]);
        Sanctum::actingAs($admin);
        // The real code validates public DNS; only DNS resolution is substituted in tests.
        $this->app->bind(SourceSyncService::class, fn () => new class extends SourceSyncService {
            protected function publicIpForHost(string $host): string { return '93.184.216.34'; }
        });
    }

    private function source(): Source
    {
        $brand = Brand::create(['name' => 'ElevenLabs', 'slug' => 'elevenlabs-lifecycle', 'status' => true]);
        $id = $this->postJson($this->root, [
            'brand_id' => $brand->id, 'name' => 'ElevenLabs Text to Speech', 'type' => 'OFFICIAL_WEBSITE',
            'url' => $this->url, 'allowed_domains' => ['https://elevenlabs.io/'],
        ])->assertCreated()->assertJsonPath('data.status', 'NOT_SYNCED')->json('data.id');
        return Source::findOrFail($id);
    }

    private function page(string $html = '<html><body><h1>Text to Speech</h1><p>Generate speech using AI voices.</p></body></html>', int $status = 200): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            'https://elevenlabs.io/robots.txt' => Http::response('User-agent: *', 200),
            $this->url => Http::response($html, $status),
        ]);
    }

    public function test_new_source_and_test_only_leave_sync_state_untouched(): void
    {
        $source = $this->source(); $this->page();
        $this->assertNull($source->last_synced_at);
        $this->postJson($this->root.'/'.$source->id.'/test')->assertOk()
            ->assertJsonPath('data.ok', true)->assertJsonPath('data.source.status', 'NOT_SYNCED')
            ->assertJsonPath('data.source.last_synced_at', null);
        $source->refresh();
        $this->assertNotNull($source->last_tested_at);
        $this->assertSame('PASSED', $source->last_test_status);
        $this->assertSame(200, $source->last_http_status);
        $this->assertNull($source->last_synced_at);
        $this->assertSame(0, $source->runs()->count());
        $this->getJson($this->root)->assertOk()->assertJsonPath('data.0.status', 'NOT_SYNCED')
            ->assertJsonPath('data.0.last_synced_at', null)->assertJsonPath('data.0.last_test_status', 'PASSED');
    }

    public function test_sync_now_persists_snapshot_run_and_source_state_and_api_returns_it(): void
    {
        $source = $this->source(); $this->page();
        $this->postJson($this->root.'/'.$source->id.'/test')->assertJsonPath('data.ok', true);
        $this->postJson($this->root.'/'.$source->id.'/sync')->assertOk()
            ->assertJsonPath('data.ok', true)->assertJsonPath('data.source.status', 'SYNCED')
            ->assertJsonPath('data.source.runs.0.status', 'COMPLETED')
            ->assertJsonPath('data.source.runs.0.content_changed', true)
            ->assertJsonPath('data.source.runs.0.extracted_items', 1);
        $source->refresh();
        $this->assertNotNull($source->last_synced_at);
        $this->assertNotNull($source->next_sync_at);
        $this->assertSame(200, $source->last_http_status);
        $this->assertSame('PENDING', $source->documents()->firstOrFail()->status);
        $this->assertStringContainsString('Text to Speech', $source->documents()->first()->body);
        $this->getJson($this->root)->assertOk()->assertJsonPath('data.0.status', 'SYNCED')
            ->assertJsonPath('data.0.last_http_status', 200);
        $this->page();
        $second = $this->postJson($this->root.'/'.$source->id.'/sync');
        $second->assertJsonPath('data.source.runs.0.content_changed', false);
        $this->assertSame(2, $source->runs()->count());
        $this->assertSame(1, $source->documents()->count());
    }

    public function test_http_failure_and_successful_http_with_empty_extraction_are_sync_failures(): void
    {
        $source = $this->source(); $this->page('Unavailable', 503);
        $this->postJson($this->root.'/'.$source->id.'/sync')->assertOk()->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.source.status', 'SYNC_FAILED')->assertJsonPath('data.source.last_http_status', 503);
        $this->assertNull($source->fresh()->last_synced_at);
        $this->assertNotNull($source->fresh()->last_sync_failed_at);
        $this->assertSame('FAILED', $source->runs()->firstOrFail()->status);
        $this->page('<html><script>var foo = 1;</script><style>body {}</style></html>');
        $this->postJson($this->root.'/'.$source->id.'/sync')->assertOk()->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.source.status', 'SYNC_FAILED')->assertJsonPath('data.source.last_http_status', 200);
        $this->assertSame(0, $source->documents()->count());
        $this->assertSame(2, $source->runs()->count());
    }

    public function test_failed_snapshot_persistence_never_marks_source_synced(): void
    {
        $source = $this->source(); $this->page();
        SourceDocument::creating(function () { throw new \RuntimeException('Snapshot storage unavailable'); });
        $this->postJson($this->root.'/'.$source->id.'/sync')->assertOk()->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.source.status', 'SYNC_FAILED');
        $this->assertNull($source->fresh()->last_synced_at);
        $this->assertSame(0, $source->documents()->count());
        $this->assertSame('FAILED', $source->runs()->firstOrFail()->status);
    }

    public function test_concurrent_sync_request_is_rejected_without_duplicate_run(): void
    {
        $source = $this->source();
        $source->update(['status' => 'SYNCING', 'sync_started_at' => now()]);
        $this->postJson($this->root.'/'.$source->id.'/sync')->assertStatus(409);
        $this->assertSame(0, $source->runs()->count());
        $this->assertSame('SYNCING', $source->fresh()->status);
    }

    public function test_failed_test_records_error_without_changing_sync_status(): void
    {
        $source = $this->source(); $this->page('Access denied', 403);
        $this->postJson($this->root.'/'.$source->id.'/test')->assertOk()
            ->assertJsonPath('data.ok', false)->assertJsonPath('data.source.status', 'NOT_SYNCED')
            ->assertJsonPath('data.source.last_test_status', 'FAILED');
        $this->assertNotNull($source->fresh()->last_test_error);
        $this->assertNull($source->fresh()->last_synced_at);
        $this->assertSame(0, $source->runs()->count());
    }

    public function test_interrupted_sync_can_be_retried_and_old_run_is_preserved(): void
    {
        $source = $this->source(); $this->page();
        $source->update(['status' => 'SYNCING', 'sync_started_at' => now()->subMinutes(4)]);
        $old = $source->runs()->create(['status' => 'RUNNING', 'started_at' => now()->subMinutes(4)]);
        $this->postJson($this->root.'/'.$source->id.'/sync')->assertOk()
            ->assertJsonPath('data.ok', true)->assertJsonPath('data.source.status', 'SYNCED');
        $this->assertSame('FAILED', $old->fresh()->status);
        $this->assertSame('COMPLETED', $source->runs()->first()->status);
        $this->assertSame(2, $source->runs()->count());
    }

    public function test_csv_import_only_succeeds_after_rows_are_persisted(): void
    {
        $brand = Brand::create(['name' => 'CSV Brand', 'slug' => 'csv-brand', 'status' => true]);
        $source = Source::create(['brand_id' => $brand->id, 'name' => 'Facts CSV', 'type' => 'CSV_IMPORT', 'enabled' => true]);
        try { app(SourceSyncService::class)->csv($source, "title,body\n"); $this->fail('Empty CSV must fail.'); }
        catch (\Illuminate\Validation\ValidationException) { /* Expected. */ }
        $this->assertSame('SYNC_FAILED', $source->fresh()->status);
        $this->assertNull($source->fresh()->last_synced_at);
        $this->assertSame(0, $source->documents()->count());
        $this->assertSame(1, app(SourceSyncService::class)->csv($source, "title,body\nText to Speech,Official product facts\n"));
        $this->assertSame('SYNCED', $source->fresh()->status);
        $this->assertNotNull($source->fresh()->last_synced_at);
        $this->assertNull($source->fresh()->next_sync_at);
        $this->assertSame(2, $source->runs()->count());
    }

    public function test_due_automatic_source_sync_still_uses_the_existing_worker_job(): void
    {
        $source = $this->source(); $this->page();
        $source->update(['next_sync_at' => now()->subMinute()]);
        Queue::fake();
        app(AutomationScheduler::class)->tick();
        Queue::assertPushed(SyncSourceJob::class, fn ($job) => $job->sourceId === $source->id);
        $this->assertSame('NOT_SYNCED', $source->fresh()->status);
        (new SyncSourceJob($source->id))->handle(app(SourceSyncService::class));
        $this->assertSame('SYNCED', $source->fresh()->status);
        $this->assertNotNull($source->fresh()->last_synced_at);
    }
}
