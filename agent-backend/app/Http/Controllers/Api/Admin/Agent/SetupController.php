<?php

namespace App\Http\Controllers\Api\Admin\Agent;

use App\Http\Controllers\Controller;
use App\Jobs\AffiliateAgent\SyncSourceJob;
use App\Models\Agent\Setting;
use App\Models\Agent\Campaign;
use App\Models\Agent\Content;
use App\Models\Agent\Publication;
use App\Models\Agent\SocialAccount;
use App\Models\Agent\Source;
use App\Models\Agent\SourceDocument;
use App\Services\AffiliateAgent\SourceSyncService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class SetupController extends Controller
{
    public function overview()
    {
        $settings = Setting::current();
        $ffmpeg = new Process([config('affiliate_agent.ffmpeg'), '-version']); $ffmpeg->setTimeout(5);
        try { $ffmpeg->run(); $ffmpegOk = $ffmpeg->isSuccessful(); } catch (\Throwable) { $ffmpegOk = false; }
        $lastTick = Cache::get('affiliate-agent:last-scheduler-tick');
        $schedulerOk = $lastTick && \Carbon\Carbon::parse($lastTick)->gt(now()->subMinutes(3));
        $storageOk = is_writable(storage_path('app'));
        $queueDriver = config('queue.default');
        $redis = ['status' => 'NOT_USED'];
        if ($queueDriver === 'redis' || config('cache.default') === 'redis') {
            try { $redis = ['status' => \Illuminate\Support\Facades\Redis::ping() ? 'READY' : 'ERROR']; }
            catch (\Throwable $e) { $redis = ['status' => 'ERROR', 'message' => $e->getMessage()]; }
        }
        $provider = fn ($name, $configured) => ['status' => !$configured ? 'NOT_CONFIGURED' : match (Cache::get('affiliate-agent:provider-test:'.$name)) { 'ok' => 'READY', 'error' => 'ERROR', default => 'CONFIGURED' }];
        return ApiResponse::success([
            'openai' => (bool) config('affiliate_agent.openai_key'), 'elevenlabs' => (bool) config('affiliate_agent.elevenlabs_key'),
            'providers' => ['openai' => $provider('openai', config('affiliate_agent.openai_key')), 'elevenlabs' => $provider('elevenlabs', config('affiliate_agent.elevenlabs_key'))],
            'sources' => SourceDocument::where('status', 'APPROVED')->count(),
            'brands' => Source::with('brand:id,name')->where('enabled', true)->get(['id','brand_id','name','status','last_synced_at','last_error']),
            'accounts' => SocialAccount::all(), 'settings' => $settings,
            'social_configured' => collect(config('affiliate_agent.oauth'))->map(fn ($c) => (bool) ($c['id'] && $c['secret'])),
            'infrastructure' => ['ffmpeg' => $ffmpegOk ? 'READY' : 'ERROR', 'storage' => $storageOk ? 'READY' : 'ERROR', 'queue' => $queueDriver === 'sync' ? 'NOT_CONFIGURED' : 'CONFIGURED', 'queue_driver' => $queueDriver, 'scheduler' => $schedulerOk ? 'READY' : 'NOT_RUNNING', 'last_tick' => $lastTick, 'redis' => $redis],
            'next_generation_run' => $this->nextGeneration($settings),
        ]);
    }

    private function nextGeneration(Setting $settings): ?string
    {
        if (!$settings->generation_enabled) return null;
        $now = \Carbon\CarbonImmutable::now($settings->timezone);
        foreach (range(0, 7) as $day) {
            $date = $now->addDays($day)->setTimeFromTimeString($settings->generation_time);
            if (in_array($date->dayOfWeek, $settings->generation_days, true) && $date->greaterThan($now)) return $date->toIso8601String();
        }
        return null;
    }

    public function calendar(Request $r)
    {
        $data = $r->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from']);
        $from = \Carbon\Carbon::parse($data['from']); $to = \Carbon\Carbon::parse($data['to']);
        abort_if($from->diffInDays($to) > 40, 422, 'Calendar range must be 40 days or fewer.');
        return ApiResponse::success(['contents' => Content::with('publications')->whereBetween('planned_for', [$from->toDateString(), $to->toDateString()])->orWhereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->limit(300)->get(['id','title','status','planned_for','created_at']),
            'publications' => Publication::with('content:id,title,status')->whereBetween('scheduled_at', [$from->startOfDay(), $to->endOfDay()])->limit(500)->get()]);
    }

    public function publishing()
    {
        return ApiResponse::success(Content::with(['publications.account:id,name,platform', 'currentVersion:id,content_id,number'])->latest()->limit(50)->get(['id','title','status','current_version_id','final_approved_version_id','created_at']));
    }

    public function sources()
    {
        return ApiResponse::success(Source::with(['brand:id,name', 'documents', 'runs'])->latest()->get());
    }

    public function campaigns() { return ApiResponse::success(Campaign::with('brand:id,name')->latest()->get()); }

    public function saveCampaign(Request $r, ?Campaign $campaign = null)
    {
        $data = $r->validate(['brand_id' => 'required|exists:brands,id,deleted_at,NULL', 'affiliate_product_id' => 'nullable|exists:affiliate_products,id,deleted_at,NULL', 'name' => 'required|string|max:255', 'brief' => 'required|string|max:6000', 'priority' => 'required|integer|between:0,100', 'monthly_target' => 'required|integer|between:1,120', 'starts_on' => 'nullable|date', 'ends_on' => 'nullable|date|after_or_equal:starts_on', 'enabled' => 'required|boolean']);
        if (!empty($data['affiliate_product_id']) && !\App\Models\AffiliateProduct::whereKey($data['affiliate_product_id'])->where('brand_id', $data['brand_id'])->exists()) return response()->json(['message' => 'Product must belong to the selected brand.'], 422);
        if ($campaign) $campaign->update($data); else $campaign = Campaign::create($data);
        return ApiResponse::success($campaign, 'Campaign saved.', $campaign->wasRecentlyCreated ? 201 : 200);
    }

    public function saveSource(Request $r, ?Source $source = null)
    {
        $data = $r->validate(['brand_id' => 'required|exists:brands,id,deleted_at,NULL', 'affiliate_product_id' => 'nullable|exists:affiliate_products,id,deleted_at,NULL', 'name' => 'required|string|max:255', 'type' => ['required', Rule::in(['API','OFFICIAL_WEBSITE','OFFICIAL_DOCS','RSS_OR_FEED','CSV_IMPORT','MANUAL','WEBHOOK'])], 'url' => 'nullable|url:https|max:2000', 'credentials' => 'nullable|string|max:4000', 'notes' => 'nullable|string|max:20000', 'allowed_domains' => 'nullable|array|max:10', 'allowed_domains.*' => 'string|max:255', 'priority' => 'integer|between:0,100', 'frequency_hours' => 'integer|between:1,720', 'enabled' => 'boolean']);
        $domains = [];
        foreach ($data['allowed_domains'] ?? [] as $entry) {
            $entry = strtolower(trim($entry));
            if (str_contains($entry, '://')) {
                $parts = parse_url($entry);
                if (!$parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
                    throw ValidationException::withMessages(['allowed_domains' => 'Use hostnames or HTTPS URLs without credentials or ports.']);
                }
                $entry = $parts['host'] ?? '';
            }
            // A source must be addressed by its HTTPS hostname; a raw IP is never an allowed source.
            if (filter_var($entry, FILTER_VALIDATE_IP)) continue;
            if (!str_contains($entry, '.') || !filter_var($entry, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                throw ValidationException::withMessages(['allowed_domains' => 'Use a valid domain such as elevenlabs.io.']);
            }
            $domains[] = $entry;
        }
        $data['allowed_domains'] = array_values(array_unique($domains));
        if (!empty($data['affiliate_product_id']) && !\App\Models\AffiliateProduct::whereKey($data['affiliate_product_id'])->where('brand_id', $data['brand_id'])->exists()) return response()->json(['message' => 'Product must belong to brand.'], 422);
        if (in_array($data['type'] ?? null, ['OFFICIAL_WEBSITE', 'OFFICIAL_DOCS', 'RSS_OR_FEED', 'API'], true) && empty($data['url'])) return response()->json(['message' => 'Source URL required.'], 422);
        if (!empty($data['url'])) {
            $host = strtolower((string) parse_url($data['url'], PHP_URL_HOST));
            if (filter_var($host, FILTER_VALIDATE_IP) || !in_array($host, $data['allowed_domains'], true)) {
                throw ValidationException::withMessages(['allowed_domains' => 'Add the source hostname "'.$host.'" to allowed domains (not its IP address).']);
            }
        }
        if ($source) { if ($source->brand_id !== (int) $data['brand_id']) return response()->json(['message' => 'Brand cannot be changed on an existing source.'], 422); $source->update($data); }
        else $source = Source::create($data);
        $created = $source->wasRecentlyCreated;
        return ApiResponse::success($source->refresh(), 'Source saved.', $created ? 201 : 200);
    }

    public function testSource(Source $source, SourceSyncService $service)
    {
        return ApiResponse::success($service->test($source));
    }

    public function sync(Source $source, SourceSyncService $service)
    {
        abort_unless($source->enabled, 409, 'Enable this source before syncing.');
        abort_if($source->status === 'SYNCING' && $source->sync_started_at?->gt(now()->subMinutes(3)), 409, 'Source is already syncing.');
        try {
            $service->sync($source);
            return ApiResponse::success(['ok' => true, 'source' => $source->fresh()->load('runs', 'documents')], 'Source synchronized.');
        } catch (\Throwable $e) {
            if ($source->fresh()->status === 'SYNCING') abort(409, 'Source is already syncing.');
            if (!$e instanceof ValidationException) report($e);
            return ApiResponse::success(['ok' => false, 'source' => $source->fresh()->load('runs', 'documents'), 'message' => $e->getMessage()], 'Source synchronization failed.');
        }
    }

    public function importCsv(Source $source, Request $r, SourceSyncService $service)
    {
        $r->validate(['file' => 'required|file|mimes:csv,txt|max:1024']);
        return ApiResponse::success(['imported' => $service->csv($source, $r->file('file')->getContent())]);
    }

    public function webhook(Source $source, Request $r)
    {
        abort_unless($source->type === 'WEBHOOK' && $source->enabled && $source->credentials, 404);
        $signature = $r->header('X-Agent-Signature', '');
        abort_unless(hash_equals(hash_hmac('sha256', $r->getContent(), $source->credentials), $signature), 403);
        $data = $r->validate(['title' => 'required|string|max:255', 'body' => 'required|string|max:20000', 'source_url' => 'nullable|url:https|max:2000']);
        $document = $source->documents()->create(['title' => $data['title'], 'body' => $data['body'], 'source_url' => $data['source_url'] ?? null, 'sha256' => hash('sha256', $data['body']), 'status' => 'PENDING', 'synced_at' => now()]);
        return ApiResponse::success(['document_id' => $document->id, 'status' => 'PENDING'], 'Webhook data awaits admin approval.', 202);
    }

    public function approveDocument(SourceDocument $document, SourceSyncService $service)
    {
        $service->approve($document);
        return ApiResponse::success($document);
    }

    public function settings() { return ApiResponse::success(Setting::current()); }

    public function saveSettings(Request $r)
    {
        $data = $r->validate(['timezone' => 'required|timezone', 'videos_per_day' => 'required|integer|between:1,5', 'generation_time' => 'required|date_format:H:i', 'generation_days' => 'required|array|min:1|max:7', 'generation_days.*' => 'integer|between:0,6|distinct', 'horizon_days' => 'required|integer|between:1,30', 'duration_seconds' => 'required|integer|between:15,45', 'default_voice_id' => 'nullable|string|regex:/^[a-zA-Z0-9_-]{1,100}$/', 'mix' => 'required|array:tutorial,educational,use_case,tips,promotion', 'mix.*' => 'required|integer|between:0,100', 'publishing_slots' => 'required|array|min:1|max:12', 'publishing_slots.*' => 'required|date_format:H:i|distinct', 'enabled_brand_ids' => 'nullable|array', 'enabled_brand_ids.*' => 'integer|exists:brands,id,deleted_at,NULL', 'enabled_campaign_ids' => 'nullable|array', 'enabled_campaign_ids.*' => 'integer|exists:agent_campaigns,id', 'generation_enabled' => 'required|boolean', 'publishing_enabled' => 'required|boolean', 'late_policy' => ['required', Rule::in(['next_slot', 'immediate'])]]);
        if (array_sum($data['mix']) !== 100) return response()->json(['message' => 'Content mix percentages must total 100.'], 422);
        if ($data['publishing_enabled'] && (config('affiliate_agent.provider') !== 'openai' || config('affiliate_agent.voice_provider') !== 'elevenlabs' || !config('affiliate_agent.openai_key') || !config('affiliate_agent.elevenlabs_key'))) return response()->json(['message' => 'Production publishing requires configured OpenAI and ElevenLabs providers.'], 422);
        $s = Setting::current(); $s->update($data);
        return ApiResponse::success($s);
    }

    public function testProvider(string $provider)
    {
        if ($provider === 'ffmpeg') {
            $p = new Process([config('affiliate_agent.ffmpeg'), '-version']); $p->setTimeout(10); $p->run();
            return ApiResponse::success(['ok' => $p->isSuccessful(), 'message' => $p->isSuccessful() ? strtok($p->getOutput(), "\n") : 'FFmpeg unavailable']);
        }
        if ($provider === 'queue') return ApiResponse::success(['ok' => config('queue.default') !== 'sync', 'message' => 'Configured queue driver: '.config('queue.default').'. Check a running queue worker separately.']);
        if ($provider === 'scheduler') return ApiResponse::success(['ok' => true, 'message' => 'Laravel schedule is registered; verify the production cron invokes schedule:run each minute.']);
        if ($provider === 'openai') {
            if (!config('affiliate_agent.openai_key')) return ApiResponse::success(['ok' => false, 'message' => 'Set OPENAI_API_KEY.']);
            $response = Http::withToken(config('affiliate_agent.openai_key'))->timeout(15)->get('https://api.openai.com/v1/models/'.rawurlencode(config('affiliate_agent.model')));
            Cache::put('affiliate-agent:provider-test:openai', $response->successful() ? 'ok' : 'error', now()->addHours(12));
            return ApiResponse::success(['ok' => $response->successful(), 'message' => $response->successful() ? 'OpenAI model access verified.' : 'OpenAI returned HTTP '.$response->status().'. Check key and model access.']);
        }
        if ($provider === 'elevenlabs') {
            if (!config('affiliate_agent.elevenlabs_key')) return ApiResponse::success(['ok' => false, 'message' => 'Set ELEVENLABS_API_KEY.']);
            $response = Http::withHeaders(['xi-api-key' => config('affiliate_agent.elevenlabs_key')])->timeout(15)->get('https://api.elevenlabs.io/v1/user');
            Cache::put('affiliate-agent:provider-test:elevenlabs', $response->successful() ? 'ok' : 'error', now()->addHours(12));
            return ApiResponse::success(['ok' => $response->successful(), 'message' => $response->successful() ? 'ElevenLabs key verified.' : 'ElevenLabs returned HTTP '.$response->status().'. Check key and account.']);
        }
        abort(404);
    }

    public function voices()
    {
        abort_unless(config('affiliate_agent.elevenlabs_key'), 409, 'Set ELEVENLABS_API_KEY first.');
        $voices = Http::withHeaders(['xi-api-key' => config('affiliate_agent.elevenlabs_key')])->timeout(20)->get('https://api.elevenlabs.io/v1/voices')->throw()->json('voices');
        return ApiResponse::success(collect($voices)->map(fn ($v) => ['voice_id' => $v['voice_id'], 'name' => $v['name'], 'preview_url' => $v['preview_url'] ?? null])->all());
    }
}
