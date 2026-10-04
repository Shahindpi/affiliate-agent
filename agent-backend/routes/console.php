<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('agent:tick', function () {
    app(\App\Services\AffiliateAgent\AutomationScheduler::class)->tick();
})->purpose('Queue due source syncs, daily generation, and independent platform publications');

Schedule::command('agent:tick')->everyMinute()->withoutOverlapping();
