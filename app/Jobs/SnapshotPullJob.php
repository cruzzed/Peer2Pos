<?php

namespace App\Jobs;

use App\Models\Node;
use App\Services\SyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SnapshotPullJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $peerUrl) {}

    public function handle(SyncService $syncService): void
    {
        $response = Http::timeout(30)
            ->withToken(config('app.workgroup_token'))
            ->get("{$this->peerUrl}/api/sync/snapshot");

        if (! $response->successful()) {
            Log::warning("Snapshot pull from {$this->peerUrl} failed: HTTP {$response->status()}");

            return;
        }

        foreach ($response->json('records', []) as $record) {
            $syncService->receiveRecord($record['type'], $record['data']);
        }

        Node::where('url', $this->peerUrl)->update(['last_seen_at' => now()]);
    }
}
