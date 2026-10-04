<?php

namespace App\Jobs\AffiliateAgent;

use App\Models\Agent\Setting;
use App\Services\AffiliateAgent\GenerationWorkflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PlanDailyContentJob implements ShouldQueue
{
    use Queueable;
    public int $tries = 2;
    public function __construct(public int $userId) { $this->onQueue('agent-generation'); }
    public function handle(GenerationWorkflow $workflow): void { $workflow->launch(Setting::current(), $this->userId); }
}
