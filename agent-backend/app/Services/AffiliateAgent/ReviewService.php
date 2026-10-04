<?php

namespace App\Services\AffiliateAgent;

use App\Jobs\AffiliateAgent\GenerateContentJob;
use App\Jobs\AffiliateAgent\ReviseContentJob;
use App\Models\Agent\Approval;
use App\Models\Agent\Content;
use App\Models\Agent\ContentVersion;
use App\Models\Agent\Feedback;
use App\Models\AffiliateProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public function create(array $data, int $userId): Content
    {
        $snapshot = app(ContentSchema::class)->validate($data['snapshot']);
        if (!empty($data['affiliate_product_id'])) {
            $product = AffiliateProduct::findOrFail($data['affiliate_product_id']);
            if ($product->brand_id !== ($data['brand_id'] ?? null)) throw ValidationException::withMessages(['brand_id' => 'Product must belong to the selected brand.']);
        }
        return DB::transaction(function () use ($data, $snapshot, $userId) {
            $content = Content::create(['title' => $data['title'], 'brand_id' => $data['brand_id'] ?? null, 'affiliate_product_id' => $data['affiliate_product_id'] ?? null, 'created_by' => $userId, 'locks' => [], 'status' => 'GENERATING']);
            GenerateContentJob::dispatch($content->id, $snapshot, $userId)->afterCommit();
            return $content;
        });
    }

    public function current(Content $content, int $expected): ContentVersion
    {
        if ($content->current_version_id !== $expected) abort(409, 'The current version changed. Refresh and review it before continuing.');
        return $content->versions()->findOrFail($expected);
    }

    public function invalidate(Content $content): void
    {
        $content->approvals()->whereNull('invalidated_at')->update(['invalidated_at' => now()]);
        $content->publications()->whereIn('status', ['SCHEDULED', 'EXPORT_READY', 'FAILED'])->update(['status' => 'CANCELLED', 'error' => 'Approval invalidated by a content change.']);
        $content->final_approved_version_id = null;
    }

    public function revision(int $id, array $data, int $userId): Feedback
    {
        return DB::transaction(function () use ($id, $data, $userId) {
            $content = Content::lockForUpdate()->findOrFail($id);
            $base = $this->current($content, $data['expected_version_id']);
            if ($content->status === 'REVISION_PENDING') abort(409, 'A revision is already in progress.');
            if (Feedback::whereDate('created_at', today())->count() >= config('affiliate_agent.max_daily_revisions')) abort(429, 'Daily revision limit reached.');
            app(ContentSchema::class)->validateTarget($data['target'] ?? 'all', $base->snapshot);
            if (isset($data['patch'])) {
                $new = app(ContentSchema::class)->validate(array_replace($base->snapshot, $data['patch']));
                app(RevisionPlanner::class)->plan($base->snapshot, $new, $content->locks);
            }
            $feedback = Feedback::create(['content_id' => $id, 'base_version_id' => $base->id, 'created_by' => $userId, 'feedback' => $data['feedback'], 'target' => $data['target'] ?? 'all', 'locks' => $content->locks, 'patch' => $data['patch'] ?? null, 'status' => 'QUEUED']);
            $this->invalidate($content);
            $content->status = 'REVISION_PENDING';
            $content->save();
            ReviseContentJob::dispatch($feedback->id)->afterCommit();
            return $feedback;
        });
    }

    public function append(Content $content, array $result, int $userId, ?int $restoredFrom = null): ContentVersion
    {
        $version = $content->versions()->create([
            'number' => (int) $content->versions()->max('number') + 1,
            'parent_version_id' => $content->current_version_id,
            'restored_from_id' => $restoredFrom,
            'snapshot' => $result['snapshot'], 'artifacts' => $result['artifacts'],
            'changes' => $result['changes'], 'steps' => $result['steps'], 'qa' => $result['qa'],
            'snapshot_hash' => app(ContentSchema::class)->hash($result['snapshot'], $result['artifacts']),
            'mock' => !empty($result['mock']) || !empty($result['artifacts']['voice']['mock']) || !empty($result['artifacts']['video']['mock']),
            'created_by' => $userId,
        ]);
        $this->invalidate($content);
        $content->checkpoint = null;
        $content->current_version_id = $version->id;
        $content->status = 'REVIEW_PENDING';
        $content->save();
        return $version;
    }

    public function locks(int $id, int $expected, array $locks): Content
    {
        return DB::transaction(function () use ($id, $expected, $locks) {
            $c = Content::lockForUpdate()->findOrFail($id);
            $v = $this->current($c, $expected);
            if ($c->status === 'REVISION_PENDING') abort(409, 'Wait for the current revision before changing locks.');
            foreach ($locks as $lock) app(ContentSchema::class)->validateTarget($lock, $v->snapshot);
            if (in_array('all', $locks, true)) throw ValidationException::withMessages(['locks' => 'Select specific components to lock.']);
            if ($c->locks !== $locks) {
                $this->invalidate($c);
                $c->locks = $locks;
                $c->status = 'REVIEW_PENDING';
                $c->save();
            }
            return $c;
        });
    }

    public function approve(int $id, int $expected, int $userId): Content
    {
        return DB::transaction(function () use ($id, $expected, $userId) {
            $c = Content::lockForUpdate()->findOrFail($id);
            $v = $this->current($c, $expected);
            if (!in_array($c->status, ['REVIEW_PENDING', 'FINAL_APPROVED'], true)) abort(409, 'This content is not ready for approval.');
            if (empty($v->qa['passed'])) throw ValidationException::withMessages(['qa' => 'Resolve QA failures before approval.']);
            app(PublishingAgent::class)->verifyVersion($v);
            if ($c->final_approved_version_id === $v->id) return $c;
            Approval::create(['content_id' => $id, 'version_id' => $v->id, 'approved_by' => $userId, 'snapshot_hash' => $v->snapshot_hash, 'approved_at' => now()]);
            $c->final_approved_version_id = $v->id;
            $c->status = 'FINAL_APPROVED';
            $c->save();
            return $c;
        });
    }

    public function reject(int $id, int $expected, string $reason, int $userId): Content
    {
        return DB::transaction(function () use ($id, $expected, $reason, $userId) {
            $c = Content::lockForUpdate()->findOrFail($id);
            $this->current($c, $expected);
            if ($c->status === 'REVISION_PENDING') abort(409, 'Wait for the revision to complete.');
            $this->invalidate($c);
            Feedback::create(['content_id' => $id, 'base_version_id' => $expected, 'feedback' => $reason, 'target' => 'all', 'locks' => $c->locks, 'created_by' => $userId, 'status' => 'REJECTED']);
            $c->status = 'REJECTED';
            $c->save();
            return $c;
        });
    }

    public function restore(int $id, int $expected, int $sourceId, int $userId): ContentVersion
    {
        return DB::transaction(function () use ($id, $expected, $sourceId, $userId) {
            $c = Content::lockForUpdate()->findOrFail($id);
            $current = $this->current($c, $expected);
            if ($c->status === 'REVISION_PENDING') abort(409, 'Wait for the revision before restoring.');
            $source = $c->versions()->findOrFail($sourceId);
            // Restoring is an explicit edit; locks still apply.
            $changes = app(RevisionPlanner::class)->changes($current->snapshot, $source->snapshot);
            if ($changes) app(RevisionPlanner::class)->plan($current->snapshot, $source->snapshot, $c->locks);
            foreach (['voice', 'video'] as $component) {
                if ($current->artifacts[$component] !== $source->artifacts[$component]) {
                    if (in_array($component, $c->locks, true)) throw ValidationException::withMessages(['locks' => "Restoring would replace locked $component."]);
                    $changes[] = ['component' => $component, 'before' => $current->artifacts[$component]['sha256'], 'after' => $source->artifacts[$component]['sha256']];
                }
            }
            app(PublishingAgent::class)->verifyVersion($source);
            $qa = app(ComplianceQaAgent::class)->check($source->snapshot, $source->artifacts);
            return $this->append($c, ['snapshot' => $source->snapshot, 'artifacts' => $source->artifacts, 'changes' => $changes, 'steps' => ['restore', 'qa'], 'qa' => $qa], $userId, $source->id);
        });
    }
}
