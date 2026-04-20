<?php

use App\Services\SyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    app(SyncService::class)->syncAll();
})->everyMinute()->name('sync-all-peers')->withoutOverlapping();
