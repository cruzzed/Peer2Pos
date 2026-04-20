<?php

namespace App\Jobs;

use App\Services\SyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PushSyncJob implements ShouldQueue
{
    use Queueable;

    public function handle(SyncService $syncService): void
    {
        $syncService->syncAll();
    }
}
