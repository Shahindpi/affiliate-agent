<?php

namespace App\Jobs\AffiliateAgent;

use App\Models\Agent\Source;
use App\Services\AffiliateAgent\SourceSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSourceJob implements ShouldQueue
{
    use Queueable;
    public int $tries = 1;
    public function __construct(public int $sourceId) { $this->onQueue('agent-sources'); }
    public function handle(SourceSyncService $service): void
    {
        $source = Source::findOrFail($this->sourceId);
        if (!$source->enabled || $source->status === 'SYNCING') return;
        $service->sync($source);
    }
}
