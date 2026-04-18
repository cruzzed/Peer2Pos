<?php

namespace App\Concerns;

use App\Models\Node;
use App\Models\SyncStatus;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Syncable
{
    public static function bootSyncable(): void
    {
        static::created(function ($model) {
            $model->createSyncStatusesForAllPeers();
        });
    }

    public function syncStatuses(): MorphMany
    {
        return $this->morphMany(SyncStatus::class, 'syncable');
    }

    public function createSyncStatusesForAllPeers(): void
    {
        $peers = Node::peers();

        foreach ($peers as $peer) {
            SyncStatus::firstOrCreate(
                [
                    'syncable_type' => static::class,
                    'syncable_id' => $this->getKey(),
                    'node_id' => $peer->id,
                ],
                ['status' => 'pending']
            );
        }
    }

    public function pendingSyncNodes(): \Illuminate\Database\Eloquent\Collection
    {
        return Node::whereHas('syncStatuses', function ($query) {
            $query->where('syncable_type', static::class)
                ->where('syncable_id', $this->getKey())
                ->where('status', 'pending');
        })->get();
    }

    public function isSyncedTo(string $nodeId): bool
    {
        return $this->syncStatuses()
            ->where('node_id', $nodeId)
            ->where('status', 'synced')
            ->exists();
    }
}
