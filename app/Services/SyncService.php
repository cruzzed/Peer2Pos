<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Location;
use App\Models\Node;
use App\Models\Product;
use App\Models\QtyType;
use App\Models\Supplier;
use App\Models\SyncStatus;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Database\Eloquent\Model;
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
                    ->withToken(config('app.workgroup_token'))
                    ->post("{$peer->url}/api/sync/receive", [
                        'type' => $syncStatus->syncable_type,
                        'data' => $this->serializeModel($model),
                        'sender_node_id' => config('app.node_id'),
                    ]);

                if ($response->successful()) {
                    $syncStatus->markSynced();
                    $pushed++;
                } else {
                    $syncStatus->markFailed();
                    $failed++;
                }
            } catch (\Throwable $e) {
                Log::warning("Sync to {$peer->name} failed: ".$e->getMessage());
                $syncStatus->markFailed();
                $failed++;
            }
        }

        if ($pushed > 0) {
            $peer->update(['last_seen_at' => now()]);
        }

        return ['pushed' => $pushed, 'failed' => $failed];
    }

    /**
     * Receive a record pushed from a peer and upsert it locally.
     * Uses saveQuietly() / forceFill() throughout to prevent re-triggering
     * the Syncable broadcast hooks (loop protection).
     */
    public function receiveRecord(string $type, array $data, ?string $senderNodeId = null): bool
    {
        $allowedTypes = [
            Category::class,
            Supplier::class,
            Location::class,
            Product::class,
            Transaction::class,
        ];

        if (! in_array($type, $allowedTypes)) {
            return false;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $type;

        $existing = $modelClass::find($data['id'] ?? null);

        if ($existing) {
            $incomingUpdatedAt = $data['updated_at'] ?? null;

            if ($incomingUpdatedAt && $existing->updated_at < $incomingUpdatedAt) {
                $existing->forceFill($data)->saveQuietly();
            }
        } else {
            (new $modelClass)->forceFill($data)->saveQuietly();
        }

        // Handle nested relations
        $record = $modelClass::find($data['id'] ?? null);

        if ($record instanceof Product && isset($data['qty_types'])) {
            foreach ($data['qty_types'] as $qtData) {
                $discountData = $qtData['discount'] ?? null;
                unset($qtData['discount']);

                $existingQt = QtyType::find($qtData['id'] ?? null);

                if ($existingQt) {
                    $existingQt->forceFill($qtData)->saveQuietly();
                } else {
                    (new QtyType)->forceFill($qtData)->saveQuietly();
                }

                if ($discountData) {
                    // Strip the integer PK — let updateOrCreate match on qty_type_id only
                    unset($discountData['id']);
                    Discount::updateOrCreate(
                        ['qty_type_id' => $qtData['id']],
                        $discountData
                    );
                }
            }
        }

        if ($record instanceof Transaction && isset($data['transaction_items'])) {
            foreach ($data['transaction_items'] as $itemData) {
                $existingItem = TransactionItem::find($itemData['id'] ?? null);

                if ($existingItem) {
                    $existingItem->forceFill($itemData)->saveQuietly();
                } else {
                    (new TransactionItem)->forceFill($itemData)->saveQuietly();
                }
            }
        }

        // Mark the sender's SyncStatus as synced so we don't push this record back to them
        if ($senderNodeId && isset($data['id'])) {
            SyncStatus::updateOrCreate(
                [
                    'syncable_type' => $type,
                    'syncable_id' => $data['id'],
                    'node_id' => $senderNodeId,
                ],
                ['status' => 'synced', 'synced_at' => now()]
            );
        }

        return true;
    }

    /**
     * Serialize a model for transmission, including nested relations where needed.
     */
    private function serializeModel(Model $model): array
    {
        if ($model instanceof Product) {
            $data = $model->toArray();
            $data['qty_types'] = $model->qtyTypes()->with('discount')->get()->map(function (QtyType $qt) {
                return array_merge($qt->toArray(), ['discount' => $qt->discount?->toArray()]);
            })->all();

            return $data;
        }

        if ($model instanceof Transaction) {
            $data = $model->toArray();
            $data['transaction_items'] = $model->items()->get()->toArray();

            return $data;
        }

        return $model->toArray();
    }

    /**
     * Create pending SyncStatus rows for all existing syncable records targeting a new peer.
     * Called after a node joins the workgroup to enable the outbound push of local data.
     */
    public function seedPendingForPeer(Node $peer): void
    {
        $classes = [Category::class, Supplier::class, Location::class, Product::class, Transaction::class];

        foreach ($classes as $class) {
            foreach ($class::all() as $record) {
                SyncStatus::firstOrCreate(
                    [
                        'syncable_type' => $class,
                        'syncable_id' => $record->getKey(),
                        'node_id' => $peer->id,
                    ],
                    ['status' => 'pending']
                );
            }
        }
    }
}
