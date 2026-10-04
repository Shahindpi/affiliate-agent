<?php

namespace App\Services\AffiliateAgent;

use App\Jobs\AffiliateAgent\PublishContentJob;
use App\Models\Agent\Content;
use App\Models\Agent\ContentVersion;
use App\Models\Agent\Publication;
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
            $publication = Publication::firstOrCreate(['version_id' => $v->id, 'platform' => $data['platform']], ['content_id' => $c->id, 'snapshot_hash' => $v->snapshot_hash, 'mode' => $data['mode'], 'status' => 'SCHEDULED', 'scheduled_at' => $data['scheduled_at'] ?? now(), 'created_by' => $userId]);
            if (!$publication->wasRecentlyCreated) {
                if (in_array($publication->status, ['CANCELLED', 'FAILED'], true)) $publication->update(['mode' => $data['mode'], 'status' => 'SCHEDULED', 'scheduled_at' => $data['scheduled_at'] ?? now(), 'error' => null]);
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
            if ($p->status !== 'SCHEDULED' || $p->scheduled_at->isFuture()) return;
            try { $v = $this->approved($c, $p->version_id); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $p->update(['status' => 'CANCELLED', 'error' => $e->getMessage()]); return; }
            if (!hash_equals($p->snapshot_hash, $v->snapshot_hash)) { $p->update(['status' => 'CANCELLED', 'error' => 'Publication manifest changed.']); return; }
            // Explicit manual/mock adapters. A future official adapter must run inside this guard
            // and use platform-supported idempotency before any real send.
            if ($p->mode === 'manual') $p->update(['status' => 'EXPORT_READY']);
            elseif ($p->mode === 'mock') $p->update(['status' => 'MOCK_PUBLISHED', 'external_id' => 'mock-'.$p->id]);
            else throw ValidationException::withMessages(['mode' => 'Real publishing is unavailable until an official adapter is configured.']);
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
