<?php

namespace App\Services\AffiliateAgent;

use App\Jobs\AffiliateAgent\{PlanDailyContentJob,PublishContentJob,SyncSourceJob};
use App\Models\Agent\{Content,Publication,Setting,Source};
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AutomationScheduler
{
    public function tick(): void
    {
        Cache::put('affiliate-agent:last-scheduler-tick', now()->toIso8601String(), now()->addDays(2));
        $settings = Setting::current();
        Source::where('enabled', true)->whereNotNull('next_sync_at')->where('next_sync_at', '<=', now())->limit(10)->get()->each(function ($s) {
            $s->update(['next_sync_at' => now()->addHours($s->frequency_hours)]);
            SyncSourceJob::dispatch($s->id);
        });
        Publication::whereIn('status', ['SCHEDULED','RETRY_PENDING','PROCESSING'])->where('scheduled_at', '<=', now())->limit(30)->get()->each(function ($p) {
            // Poll processing every five minutes, without repeating a completed upload.
            if ($p->status !== 'SCHEDULED' && $p->updated_at->gt(now()->subMinutes(5))) return;
            PublishContentJob::dispatch($p->id);
        });
        if (!$settings->generation_enabled) return;
        $local = now($settings->timezone);
        if (!in_array($local->dayOfWeek, $settings->generation_days, true) || $local->format('H:i') < $settings->generation_time) return;
        Cache::lock('agent-daily-plan:'.$local->toDateString(), 60)->block(2, function () use ($settings, $local) {
            $count = Content::whereDate('planned_for', $local->toDateString())->count();
            $userId = User::whereHas('role', fn ($q) => $q->where('slug', 'admin'))->orderBy('id')->value('id');
            if (!$userId) return;
            for ($i = $count; $i < $settings->videos_per_day; $i++) {
                // Reserve a row in the worker itself; dedupe job enqueue per local date/slot.
                $key = 'agent-plan-slot:'.$local->toDateString().':'.$i;
                if (Cache::add($key, true, now()->addDay())) PlanDailyContentJob::dispatch($userId);
            }
        });
    }

    public function nextSlot(Setting $settings): \Carbon\CarbonImmutable
    {
        $now = \Carbon\CarbonImmutable::now($settings->timezone);
        foreach (range(0, 14) as $day) foreach ($settings->publishing_slots as $slot) {
            $candidate = $now->addDays($day)->setTimeFromTimeString($slot);
            if ($candidate->greaterThan($now)) return $candidate->utc();
        }
        throw new \RuntimeException('No publishing slot is configured.');
    }

    public function afterApproval(Content $content, int $userId): void
    {
        $settings = Setting::current();
        $when = $settings->late_policy === 'immediate' ? now() : $this->nextSlot($settings);
        foreach (ContentSchema::PLATFORMS as $platform) {
            $account = \App\Models\Agent\SocialAccount::where('platform', $platform)->where('publishing_enabled', true)->where('status', 'CONNECTED')->first();
            $mode = $account && $settings->publishing_enabled && !$content->currentVersion->mock ? 'api' : 'manual';
            app(PublishingAgent::class)->schedule($content->id, ['expected_version_id' => $content->current_version_id, 'platform' => $platform, 'mode' => $mode, 'social_account_id' => $account?->id, 'scheduled_at' => $when], $userId);
        }
    }
}
