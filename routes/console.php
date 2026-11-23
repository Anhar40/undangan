<?php

use App\Console\Commands\ExpireInvitationPreviews;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Optional for VPS/cron. Shared hosting is also covered by the on-request sweep middleware.
Schedule::command(ExpireInvitationPreviews::class . ' --prune-days=30')->hourly();
