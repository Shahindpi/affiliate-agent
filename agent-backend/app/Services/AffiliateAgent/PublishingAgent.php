<?php

namespace App\Services\AffiliateAgent;

use App\Jobs\AffiliateAgent\PublishContentJob;
use App\Models\Agent\Content;
use App\Models\Agent\ContentVersion;
use App\Models\Agent\Publication;
use App\Models\Agent\SocialAccount;
use App\Models\Agent\Setting;
use App\Services\AffiliateAgent\Publishers\{SocialPublisherInterface,PinterestPublisher,InstagramPublisher,TikTokPublisher,FacebookPublisher,YouTubePublisher};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishingAgent
{
    public function verifyVersion(ContentVersion $version): void
    {
        $schema = app(ContentSchema::class);
        if (!hash_equals($version->snapshot_hash, $schema->hash($version->snapshot, $version->artifacts))) abort(409, 'Version manifest does not match its approved hash.');
        foreach (['video', 'voice'] as $key) if (!app(AssetStore::class)->verify($version->artifacts[$key] ?? [])) abort(409, 'A version asset is missing or has changed.');
        foreach ($version->artifacts['scenes'] ?? [] as $asset) if (!app(AssetStore::class)->verify($asset)) abort(409, 'A scene asset is missing or has changed.');
    }

    public function approved(Content $content, int $versionId): ContentVersion
    {
        if ($content->status !== 'FINAL_APPROVED' || $content->current_version_id !== $versionId || $content->final_approved_version_id !== $versionId) abort(409, 'Publishing requires the exact current FINAL_APPROVED version.');
        $v = $content->versions()->findOrFail($versionId);
        if (!$content->approvals()->where('version_id', $versionId)->where('snapshot_hash', $v->snapshot_hash)->whereNull('invalidated_at')->exists()) abort(409, 'No valid approval exists for this version.');
        $this->verifyVersion($v);
        return $v;
    }

    public function schedule(int $contentId, array $data, int $userId): Publication
    {
        return DB::transaction(function () use ($contentId, $data, $userId) {
            $c = Content::lockForUpdate()->findOrFail($contentId);
            $v = $this->approved($c, $data['expected_version_id']);
            $account = null;
            if ($data['mode'] === 'api') {
                $settings = Setting::current();
                if (!$settings->publishing_enabled) throw ValidationException::withMessages(['mode' => 'Enable production publishing in Settings after checking readiness.']);
                $account = SocialAccount::whereKey($data['social_account_id'] ?? 0)->where('platform', $data['platform'])->where('publishing_enabled', true)->where('status', 'CONNECTED')->first();
                if (!$account) throw ValidationException::withMessages(['social_account_id' => 'Select a connected account on this platform with publishing enabled.']);
                if ($v->mock) throw ValidationException::withMessages(['mode' => 'Mock content cannot be sent to a real social account.']);
            }
            $publication = Publication::firstOrCreate(['version_id' => $v->id, 'platform' => $data['platform']], ['content_id' => $c->id, 'snapshot_hash' => $v->snapshot_hash, 'mode' => $data['mode'], 'social_account_id' => $account?->id, 'status' => 'SCHEDULED', 'scheduled_at' => $data['scheduled_at'] ?? now(), 'created_by' => $userId]);
            if (!$publication->wasRecentlyCreated) {
                if (in_array($publication->status, ['CANCELLED', 'FAILED', 'SCHEDULED'], true) && !$publication->started_at) $publication->update(['mode' => $data['mode'], 'social_account_id' => $account?->id, 'status' => 'SCHEDULED', 'scheduled_at' => $data['scheduled_at'] ?? now(), 'error' => null]);
                else return $publication;
            }
            PublishContentJob::dispatch($publication->id)->delay($publication->scheduled_at)->afterCommit();
            return $publication;
        });
    }

    public function publish(int $publicationId): void
    {
        $record = Publication::findOrFail($publicationId);
        DB::transaction(function () use ($record) {
            // All review writes take the same row lock. Approval cannot be invalidated mid-send.
            $c = Content::lockForUpdate()->findOrFail($record->content_id);
            $p = Publication::lockForUpdate()->findOrFail($record->id);
            if (!in_array($p->status, ['SCHEDULED', 'RETRY_PENDING', 'PROCESSING'], true) || $p->scheduled_at->isFuture()) return;
            try { $v = $this->approved($c, $p->version_id); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $p->update(['status' => 'CANCELLED', 'error' => $e->getMessage()]); return; }
            if (!hash_equals($p->snapshot_hash, $v->snapshot_hash)) { $p->update(['status' => 'CANCELLED', 'error' => 'Publication manifest changed.']); return; }
            if ($p->mode === 'manual') $p->update(['status' => 'EXPORT_READY']);
            elseif ($p->mode === 'mock') $p->update(['status' => 'MOCK_PUBLISHED', 'external_id' => 'mock-'.$p->id]);
            elseif ($p->mode === 'api') {
                $account = $p->account;
                if (!$account || !$account->publishing_enabled || $account->status !== 'CONNECTED' || !Setting::current()->publishing_enabled) { $p->update(['status' => 'MANUAL_ACTION_REQUIRED', 'error' => 'Account or production publishing is disabled.']); return; }
                if ($v->mock) { $p->update(['status' => 'CANCELLED', 'error' => 'Mock version cannot publish through an API.']); return; }
                $continuation = $p->status === 'PROCESSING';
                $p->update(['status' => 'SENDING', 'started_at' => $p->started_at ?? now()]);
                try {
                    $adapter = $this->adapter($p->platform);
                    $result = $continuation ? match ($p->platform) {
                        'instagram' => $adapter->finish($account, $p),
                        'tiktok' => $adapter->poll($account, $p),
                        'facebook' => $adapter->poll($account, $p),
                        default => ['status' => 'MANUAL_ACTION_REQUIRED', 'metadata' => ['reason' => 'Inspect processing state before retry.']],
                    } : $adapter->publish($v, $account, $p);
                    $p->update(['status' => $result['status'], 'external_id' => $result['id'] ?? $p->external_id, 'platform_url' => $result['url'] ?? $p->platform_url, 'response_metadata' => $result['metadata'] ?? [], 'published_at' => $result['status'] === 'PUBLISHED' ? now() : null, 'error' => null, 'retry_count' => $p->retry_count + (int) in_array($result['status'], ['RETRY_PENDING', 'PROCESSING'], true)]);
                } catch (\Throwable $e) {
                    // The provider may have accepted an upload even if its response was lost.
                    // Never automatically replay an ambiguous external send.
                    $p->update(['status' => 'MANUAL_ACTION_REQUIRED', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
                }
            } else throw ValidationException::withMessages(['mode' => 'Unknown publication mode.']);
        });
    }

    private function adapter(string $platform): SocialPublisherInterface
    {
        return app(match ($platform) {
            'pinterest' => PinterestPublisher::class, 'instagram' => InstagramPublisher::class,
            'tiktok' => TikTokPublisher::class, 'facebook' => FacebookPublisher::class,
            'youtube' => YouTubePublisher::class,
        });
    }

    public function confirmManual(int $id, string $externalId): Publication
    {
        $record = Publication::findOrFail($id);
        return DB::transaction(function () use ($record, $externalId) {
            $c = Content::lockForUpdate()->findOrFail($record->content_id);
            $p = Publication::lockForUpdate()->findOrFail($record->id);
            $this->approved($c, $p->version_id);
            if ($p->mode !== 'manual' || $p->status !== 'EXPORT_READY') abort(409, 'Only a ready manual export can be confirmed.');
            $p->update(['status' => 'PUBLISHED_MANUALLY', 'external_id' => $externalId, 'published_at' => now()]);
            return $p;
        });
    }
}
