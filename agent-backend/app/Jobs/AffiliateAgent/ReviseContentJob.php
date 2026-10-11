<?php

namespace App\Jobs\AffiliateAgent;

use App\Models\Agent\Content;
use App\Models\Agent\Feedback;
use App\Services\AffiliateAgent\RevisionAgent;
use App\Services\AffiliateAgent\ReviewService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviseContentJob implements ShouldQueue
{
    use Queueable;
    public int $tries = 40; // Lock contention may last until the owning worker or its TTL ends.
    public int $maxExceptions = 3;
    public int $timeout = 850;
    public bool $failOnTimeout = true;
    public function backoff(): array { return [30, 120, 300]; }
    public function __construct(public int $feedbackId) { $this->onQueue('agent-revisions'); }
    public function handle(RevisionAgent $agent, ReviewService $review): void
    {
        $lock = Cache::lock('agent-feedback:'.$this->feedbackId, 900);
        if (!$lock->get()) {
            if ($this->job) $this->release(30);
            return;
        }
        try {
            $feedback = Feedback::findOrFail($this->feedbackId);
            if (in_array($feedback->status, ['COMPLETED', 'FAILED'], true)) return;
            $feedback->update(['status' => 'PROCESSING', 'error' => null]);
            try {
                $result = $agent->execute($feedback);
                DB::transaction(function () use ($feedback, $result, $review) {
                    $c = Content::lockForUpdate()->findOrFail($feedback->content_id);
                    $review->current($c, $feedback->base_version_id);
                    if ($c->locks !== $feedback->locks) throw ValidationException::withMessages(['locks' => 'Locks changed during revision.']);
                    $version = $review->append($c, $result, $feedback->created_by);
                    $feedback->update(['status' => 'COMPLETED', 'result_version_id' => $version->id]);
                });
            } catch (ValidationException $e) {
                $this->markFailed(implode(' ', array_merge(...array_values($e->errors()))));
            }
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        } finally {
            $lock->release();
        }
    }
    public function failed(?\Throwable $e): void
    {
        if ($e) report($e);
        $message = config('app.debug') && $e ? 'Revision failed: '.$e->getMessage() : 'Revision failed. Check provider configuration and queue logs, then submit again.';
        $this->markFailed(mb_substr($message, 0, 2000));
    }
    private function markFailed(string $message): void
    {
        DB::transaction(function () use ($message) {
            $f = Feedback::findOrFail($this->feedbackId);
            $c = Content::lockForUpdate()->findOrFail($f->content_id);
            if ($f->status === 'COMPLETED') return;
            $f->update(['status' => 'FAILED', 'error' => $message]);
            if ($c->current_version_id === $f->base_version_id && $c->status === 'REVISION_PENDING') $c->update(['status' => 'REVIEW_PENDING']);
        });
    }
}
