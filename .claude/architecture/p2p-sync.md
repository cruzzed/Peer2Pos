# Peer2Pos — P2P Workgroup & Sync Architecture

> Design document. Written: 2026-04-19. Last updated: 2026-04-20.
> Status: **Implemented.**

---

## Overview

Each Peer2Pos node is a self-contained Laravel instance. Nodes share a **workgroup** — a set of nodes that replicate catalog and transaction data with each other in near-real-time.

Key design decisions:
- **Push-based**: nodes push their changes outward; no polling pull in normal operation
- **Last-write-wins**: conflict resolution by `updated_at` timestamp
- **Shared secret auth**: all nodes in a workgroup share a `WORKGROUP_TOKEN`
- **Queued + scheduled**: immediate dispatch via database queue + 1-minute scheduled fallback
- **Nested payloads**: `Product` carries its `QtyTypes` + `Discounts`; `Transaction` carries its `TransactionItems` — no separate sync tracking for dependent models

---

## Workgroup Join Flow (Full-Mesh Handshake)

Any node can be the entry point. When Node B joins by contacting Node A:

```
Node B (joining)              Node A (entry point)        Node C, D, ... (existing)
──────────────────────────────────────────────────────────────────────────────────
POST /api/sync/join
  { node_id, node_name,  →   Validate token
    node_url, token }         Upsert Node B
                              seedPendingForPeer(B)
                              PushSyncJob::dispatch()     ← A will push its data to B
                              Return { node: A, peers: [C, D, ...] }
                         ←

Register A + all peers (C, D ...)

── Full-mesh announce: POST /api/sync/join to C, D, ... ──
                              (each peer)
                                  Validate token          →  Upsert Node B
                                                             seedPendingForPeer(B)
                                                             PushSyncJob::dispatch()  ← C/D push to B

seedPendingForPeer(A, C, D, ...)   ← mark all local records pending for every peer
SnapshotPullJob::dispatch(A)       ← pull A's full dataset (A has workgroup state)
PushSyncJob::dispatch()            ← push B's own data to A, C, D, ...
```

After all jobs drain from the queue, every node holds the complete merged dataset.

**Key properties:**
- Node B announces itself to every existing member, not just the entry point
- Every existing member independently seeds + pushes to Node B upon receiving the announcement
- Pulling snapshot from only the entry point is sufficient — it already has the full workgroup state
- Offline peers will receive data on next scheduled `syncAll()` run or when they come back and process their queue

---

## Normal Operation: Auto-Broadcast

```
Admin edits a Product on Node A
  ↓
Product::updated event fires (Syncable trait)
  ↓
markPeersAsPending()     — upserts SyncStatus rows to status=pending
PushSyncJob::dispatch()  — queued in database jobs table
  ↓
queue:work processes PushSyncJob
  ↓
SyncService::syncAll()
  → for each peer with pending records:
      POST {peer.url}/api/sync/receive
        { type, data (with nested qty_types+discounts), sender_node_id }
  ↓
Node B: SyncController::receive()
  → SyncService::receiveRecord()
      forceFill + saveQuietly()   ← no events, no re-broadcast
      upsert nested QtyTypes, Discounts
      mark sender's SyncStatus as synced
```

Scheduled fallback: `syncAll()` runs every minute via `schedule:work` to catch any records that missed the queue (e.g., worker was down).

---

## Syncable Models & Payloads

| Model | Syncable | Payload includes |
|---|---|---|
| Category | ✓ | flat |
| Supplier | ✓ | flat |
| Location | ✓ | flat |
| Product | ✓ | `qty_types[]` each with `discount` |
| Transaction | ✓ | `transaction_items[]` |
| QtyType | — | bundled in Product |
| Discount | — | bundled in QtyType |
| TransactionItem | — | bundled in Transaction |

Dependent models are NOT tracked in `sync_statuses`. They travel inside their parent's payload.

---

## Loop Protection

When `receiveRecord()` saves a record received from a peer:
- Uses `forceFill($data)->saveQuietly()` on all models
- `saveQuietly()` suppresses all model events — `Syncable::updated` never fires
- The sender's `SyncStatus` is immediately marked `synced` so it won't be pushed back

Result: A → B → (no re-trigger back to A).

---

## API Endpoints

All sync endpoints require `Authorization: Bearer {WORKGROUP_TOKEN}` **except** `/api/sync/join` (token is in the request body for first-contact).

| Method | Path | Auth | Purpose |
|---|---|---|---|
| POST | `/api/sync/join` | body token | First-time join; register peer, return peer list |
| POST | `/api/sync/receive` | Bearer | Receive a pushed record from a peer |
| POST | `/api/sync/push` | Bearer | Manually trigger a full sync push |
| GET | `/api/sync/snapshot` | Bearer | Return full dataset for initial sync pull |

---

## Configuration (per node `.env`)

```dotenv
NODE_ID=<uuid>              # this node's identity — generate once with Str::uuid()
NODE_NAME="Terminal 1"      # human-readable label
WORKGROUP_TOKEN=<secret>    # must be identical on every node in the workgroup
APP_URL=http://192.168.x.x:8000  # reachable URL for this node from other nodes
```

```php
// config/app.php
'node_id'         => env('NODE_ID'),
'node_name'       => env('NODE_NAME', 'Terminal'),
'workgroup_token' => env('WORKGROUP_TOKEN'),
```

---

## File Change Map

| File | Status | Purpose |
|---|---|---|
| `config/app.php` | Modify | Add `workgroup_token` |
| `.env.example` | Modify | Add `NODE_ID`, `NODE_NAME`, `WORKGROUP_TOKEN` |
| `database/migrations/2026_04_19_000001_add_joined_at_to_nodes_table.php` | **Create** | Add `joined_at` nullable timestamp |
| `app/Models/Node.php` | Modify | Add `joined_at` to fillable + casts |
| `app/Http/Middleware/ValidateWorkgroupToken.php` | **Create** | Bearer token guard for sync routes |
| `bootstrap/app.php` | Modify | Register `workgroup` middleware alias |
| `app/Jobs/PushSyncJob.php` | **Create** | Queued job → `syncAll()` |
| `app/Jobs/SnapshotPullJob.php` | **Create** | Queued job → pull full snapshot from peer |
| `app/Concerns/Syncable.php` | Modify | Add `updated` hook, `markPeersAsPending()`, dispatch `PushSyncJob` |
| `app/Services/SyncService.php` | Modify | `serializeModel()`, nested upsert, loop protection, sender mark-synced, `seedPendingForPeer()` |
| `app/Http/Controllers/Api/JoinController.php` | **Create** | Handle join request, return self + peers |
| `app/Http/Controllers/Api/SyncController.php` | Modify | Add `snapshot()`, update `receive()` signature |
| `routes/api.php` | Modify | Add `join` route; protect others with `workgroup` middleware |
| `routes/console.php` | Modify | Schedule `syncAll()` every minute with overlap protection |
| `app/Filament/Resources/NodeResource/Pages/ListNodes.php` | Modify | "Join Workgroup" header action |

**5 new files, 10 modified.**

---

## Implementation Detail

### `Syncable` trait (`app/Concerns/Syncable.php`)

```php
public static function bootSyncable(): void
{
    static::created(function ($model) {
        $model->createSyncStatusesForAllPeers();
        \App\Jobs\PushSyncJob::dispatch();
    });

    static::updated(function ($model) {
        $model->markPeersAsPending();
        \App\Jobs\PushSyncJob::dispatch();
    });
}

public function markPeersAsPending(): void
{
    foreach (Node::peers() as $peer) {
        SyncStatus::updateOrCreate(
            [
                'syncable_type' => static::class,
                'syncable_id'   => $this->getKey(),
                'node_id'       => $peer->id,
            ],
            ['status' => 'pending']
        );
    }
}
```

### `SyncService` additions (`app/Services/SyncService.php`)

**`serializeModel()`** — private helper:
```php
private function serializeModel(Model $model): array
{
    if ($model instanceof Product) {
        $data = $model->toArray();
        $data['qty_types'] = $model->qtyTypes()->with('discount')->get()->map(function ($qt) {
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
```

**`receiveRecord()` — updated signature and loop protection:**
```php
public function receiveRecord(string $type, array $data, ?string $senderNodeId = null): bool
{
    // ... allowedTypes check ...
    // ... last-write-wins upsert using forceFill()->saveQuietly() ...
    // ... nested upsert for qty_types / transaction_items ...

    // Mark sender as synced so we don't re-push this record back
    if ($senderNodeId && isset($data['id'])) {
        SyncStatus::updateOrCreate(
            ['syncable_type' => $type, 'syncable_id' => $data['id'], 'node_id' => $senderNodeId],
            ['status' => 'synced', 'synced_at' => now()]
        );
    }

    return true;
}
```

**`seedPendingForPeer(Node $peer): void`** — called after join:
```php
public function seedPendingForPeer(Node $peer): void
{
    $classes = [Category::class, Supplier::class, Location::class, Product::class, Transaction::class];

    foreach ($classes as $class) {
        foreach ($class::all() as $record) {
            SyncStatus::firstOrCreate(
                ['syncable_type' => $class, 'syncable_id' => $record->getKey(), 'node_id' => $peer->id],
                ['status' => 'pending']
            );
        }
    }
}
```

### Loop protection detail

```
Node A: Product updated
  → saveQuietly() NOT used (A is the origin) → events fire → PushSyncJob dispatched
  → SyncService pushes to Node B

Node B: receiveRecord() called
  → $model->forceFill($data)->saveQuietly()   ← saveQuietly suppresses ALL events
  → Syncable::updated does NOT fire
  → No PushSyncJob dispatched from B
  → B marks A's SyncStatus as synced
```

### `ValidateWorkgroupToken` middleware

```php
public function handle(Request $request, Closure $next): Response
{
    $configured = config('app.workgroup_token');

    if (empty($configured)) {
        return response()->json(['error' => 'Workgroup token not configured.'], 500);
    }

    if (! hash_equals($configured, (string) $request->bearerToken())) {
        return response()->json(['error' => 'Unauthorized.'], 401);
    }

    return $next($request);
}
```

### `JoinController` response shape

```json
{
  "status": "joined",
  "node": {
    "id": "uuid-of-responding-node",
    "name": "Terminal 1",
    "url": "http://192.168.1.10:8000"
  },
  "peers": [
    { "id": "...", "name": "...", "url": "http://..." }
  ]
}
```

### Scheduled fallback (`routes/console.php`)

```php
Schedule::call(function () {
    app(\App\Services\SyncService::class)->syncAll();
})->everyMinute()->name('sync-all-peers')->withoutOverlapping();
```

### Filament "Join Workgroup" action flow

1. Admin fills: Peer URL + Workgroup Token (masked)
2. `POST {peer_url}/api/sync/join` with local node identity + token
3. On success: register entry-point peer + all other peers returned in `peers[]`
4. Loop through `peers[]` and POST `/api/sync/join` to each — full-mesh announce
   - Each existing peer will `seedPendingForPeer` + `PushSyncJob::dispatch()` for us
5. `SyncService::seedPendingForPeer($peer)` for **every** known peer — marks our data pending for all
6. `SnapshotPullJob::dispatch($peerUrl)` — pull full workgroup snapshot from entry-point
7. `PushSyncJob::dispatch()` — push our data to all peers
8. Success notification: "Joined workgroup. Initial sync dispatched."

---

## Gotchas

| Gotcha | Resolution |
|---|---|
| `HasUuids` auto-generates UUID on `creating` | Always `forceFill($data)->save/saveQuietly()` when UUID comes from outside; never `create()` |
| `Discount::id` is integer PK, included in `toArray()` | Strip `id` from the update-values array in `updateOrCreate(['qty_type_id' => ...], ...)` |
| `saveQuietly()` only suppresses events on that one model | Each nested model (QtyType, TransactionItem, Discount) must call `saveQuietly()` individually |
| Join endpoint must not require Bearer auth | `join` uses body token; only added to `nodes` table after token is verified |
| `seedPendingForPeer` can be slow for large datasets | Uses `firstOrCreate` (idempotent), runs synchronously in the Filament action before dispatching jobs; acceptable for LAN POS scale |
| Scheduled `syncAll()` and queued `PushSyncJob` may overlap | `withoutOverlapping()` on the schedule; queue jobs are independent and idempotent (second run finds nothing pending) |

---

## Ops Requirements (per node)

```bash
php artisan queue:work        # process PushSyncJob + SnapshotPullJob
php artisan schedule:work     # run syncAll() every minute as fallback
```

Both should autostart (Windows Task Scheduler, systemd, Supervisor, etc.) on each node.

---

## Verification Checklist

- [ ] `php artisan migrate` — `joined_at` column present on `nodes`
- [ ] Set matching `WORKGROUP_TOKEN`, distinct `NODE_ID` on two nodes
- [ ] Node B "Join Workgroup" → node A appears in B's Nodes list with `joined_at`; B appears in A's list
- [ ] Start `queue:work` on both — initial sync completes (B has A's data, A has B's data)
- [ ] Create a Category on A → appears on B after queue processes
- [ ] Edit a Product on B → update reaches A (confirms `updated` hook works)
- [ ] Stop queue on B, edit a record, restart queue → syncs via next scheduled run
- [ ] Hit `POST /api/sync/receive` without Bearer → 401
- [ ] Hit `POST /api/sync/receive` with wrong token → 401
- [ ] `php artisan test` → all existing tests pass
