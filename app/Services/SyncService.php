<?php

namespace App\Services;

use App\Models\Node;
use App\Models\SyncStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncService
{
    /**
     * Push all pending records to all reachable peers.
     */
    public function syncAll(): array
    {
        $results = [];

        foreach (Node::peers() as $peer) {
            $results[$peer->name] = $this->syncToPeer($peer);
        }

        return $results;
    }

    /**
     * Push all pending records for a specific peer.
     */
    public function syncToPeer(Node $peer): array
    {
        $pending = SyncStatus::where('node_id', $peer->id)
            ->where('status', 'pending')
            ->with('syncable')
            ->get();

        $pushed = 0;
        $failed = 0;

        foreach ($pending as $syncStatus) {
            $model = $syncStatus->syncable;
            if (! $model) {
                $syncStatus->markFailed();
                $failed++;
                continue;
            }

            try {
                $response = Http::timeout(5)
                    ->post("{$peer->url}/api/sync/receive", [
                        'type' => $syncStatus->syncable_type,
                        'data' => $model->toArray(),
                    ]);

                if ($response->successful()) {
                    $syncStatus->markSynced();
                    $pushed++;
                } else {
                    $syncStatus->markFailed();
                    $failed++;
                }
            } catch (\Throwable $e) {
                Log::warning("Sync to {$peer->name} failed: " . $e->getMessage());
                $syncStatus->markFailed();
                $failed++;
            }
        }

        // Update last_seen if at least one record was pushed successfully
        if ($pushed > 0) {
            $peer->update(['last_seen_at' => now()]);
        }

        return ['pushed' => $pushed, 'failed' => $failed];
    }

    /**
     * Receive a record from a peer and upsert it locally.
     */
    public function receiveRecord(string $type, array $data): bool
    {
        $allowedTypes = [
            'App\\Models\\Category',
            'App\\Models\\Product',
            'App\\Models\\Transaction',
            'App\\Models\\TransactionItem',
        ];

        if (! in_array($type, $allowedTypes)) {
            return false;
        }

        /** @var \Illuminate\Database\Eloquent\Model $modelClass */
        $modelClass = $type;

        // Upsert: if record exists with same id, update if newer
        $existing = $modelClass::find($data['id'] ?? null);

        if ($existing) {
            $existingUpdatedAt = $existing->updated_at;
            $incomingUpdatedAt = $data['updated_at'] ?? null;

            if ($incomingUpdatedAt && $existingUpdatedAt < $incomingUpdatedAt) {
                $existing->fill($data)->save();
            }
        } else {
            $modelClass::create($data);
        }

        return true;
    }
}
