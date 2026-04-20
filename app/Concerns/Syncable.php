<?php

namespace App\Concerns;

use App\Jobs\PushSyncJob;
use App\Models\Node;
use App\Models\SyncStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Syncable
{
    public static function bootSyncable(): void
    {
        static::created(function ($model) {
            $model->createSyncStatusesForAllPeers();
            PushSyncJob::dispatch();
        });

        static::updated(function ($model) {
            $model->markPeersAsPending();
            PushSyncJob::dispatch();
        });
    }

    public function syncStatuses(): MorphMany
    {
        return $this->morphMany(SyncStatus::class, 'syncable');
    }

    public function createSyncStatusesForAllPeers(): void
    {
        foreach (Node::peers() as $peer) {
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

    public function markPeersAsPending(): void
    {
        foreach (Node::peers() as $peer) {
            SyncStatus::updateOrCreate(
                [
                    'syncable_type' => static::class,
                    'syncable_id' => $this->getKey(),
                    'node_id' => $peer->id,
                ],
                ['status' => 'pending']
            );
        }
    }

    public function pendingSyncNodes(): Collection
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
