<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// WordPress's daily delete_expired_transients event (wp_schedule_delete_old_transients()),
// run by Laravel's scheduler instead of WP-Cron. Plugins can still unhook the action.
Schedule::call(fn () => do_action('delete_expired_transients'))
    ->daily()
    ->description('delete_expired_transients');
