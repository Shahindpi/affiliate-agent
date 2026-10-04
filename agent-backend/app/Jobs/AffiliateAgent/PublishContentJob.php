<?php

namespace App\Jobs\AffiliateAgent;

use App\Models\Agent\Publication;
use App\Services\AffiliateAgent\PublishingAgent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishContentJob implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public int $timeout = 180;
    public function backoff(): array { return [30, 120, 300]; }
    public function __construct(public int $publicationId) { $this->onQueue('agent-publishing'); }
    public function handle(PublishingAgent $agent): void { $agent->publish($this->publicationId); }
    public function failed(?\Throwable $e): void { Publication::where('id', $this->publicationId)->where('status', 'SCHEDULED')->update(['status' => 'FAILED', 'error' => 'Publishing failed. Check worker logs.']); }
}
