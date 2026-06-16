# Peer2Pos — Domain Architecture

> What the system does, its core concepts, and how the business logic is organized.
> For Filament, routing, middleware, and other Laravel-specific design, see [LARAVEL_DESIGN.md](LARAVEL_DESIGN.md).

## Purpose

Peer2Pos is a peer-to-peer Point-of-Sale (POS) system.

Multiple retail terminals (called **nodes**) run independently — each with its own local SQLite database and cashier interface — while keeping the shared catalog, products, and sales data synchronized across the workgroup without a central server.

## Core Capabilities

| Capability | Description |
|------------|-------------|
| Multi-terminal POS checkout | Barcode/item-code scanning, cart management, payment method selection, and sale recording. |
| Product catalog management | Categories, suppliers, locations, products, and multiple quantity types per product. |
| Pricing & promotions | Each quantity type has base/selling prices and optional fixed or percentage discounts with date-bounded timed discounts. |
| Peer-to-peer synchronization | Terminals discover each other via a workgroup token and sync records automatically. |
| Native Windows deployment | Runs on native Windows so PHP has direct access to hardware such as USB/COM-port receipt printers. |

## Core Entities

All primary entities use UUID primary keys, except where noted.

| Model | Purpose | Key Relationships |
|-------|---------|-------------------|
| `Node` | A terminal/peer in the workgroup. One node per install is `is_self`. | `hasMany(SyncStatus)` |
| `User` | Authenticated staff. Login accepts username or email. | — |
| `Category` | Product classification. | `belongsTo(Node)` origin, `hasMany(Product)` |
| `Supplier` | Product supplier/vendor. | `belongsTo(Node)` origin, `hasMany(Product)` |
| `Location` | Physical storage/warehouse location. | `belongsTo(Node)` origin, `hasMany(Product)` |
| `Product` | Core merchandise. Has one or more quantity types. | `belongsTo(Category, Supplier, Location, Node)` origin, `hasMany(QtyType, TransactionItem)` |
| `QtyType` | A sellable unit of a product (e.g., "Cup" qty=1, "Dozen" qty=12). Includes barcode, base price, selling price. | `belongsTo(Product)`, `hasOne(Discount)` |
| `Discount` | Optional pricing rule tied 1:1 to a `QtyType` — fixed or percent, optionally date-bounded. | `belongsTo(QtyType)` |
| `Transaction` | A completed sale/checkout. | `belongsTo(Node)` origin, `hasMany(TransactionItem)` |
| `TransactionItem` | Line item snapshot of a sale. | `belongsTo(Transaction, Product, QtyType)` |
| `SyncStatus` | Polymorphic sync queue entry per record per peer (`pending`/`synced`/`failed`). Uses auto-increment PK. | `morphTo(Syncable)`, `belongsTo(Node)` |

### Entity Relationship Diagram

```
Node (self/peer)
 ├─ Category → Product
 ├─ Supplier → Product
 ├─ Location → Product
 ├─ Product → QtyType → Discount
 │            ├─ TransactionItem
 │            └─ Transaction
 └─ Transaction → TransactionItem
```

## Key Business Concepts

- **Quantity types are the actual sellable SKUs.** A product can have a base unit (`is_base`, `qty=1`) and alternate pack sizes, each with its own barcode and price.
- **Effective price** (`QtyType::effectivePrice()`) resolves the final price after applying an enabled, date-valid discount.
- **Snapshots at sale time.** `TransactionItem` stores `product_name` and `qty_type_name` so historical sales remain accurate even if the catalog changes later.
- **Origin node.** Every syncable record records which terminal originally created it. Conflict resolution is currently based on `updated_at`.
- **Workgroup token.** A shared secret used to authenticate peer nodes during sync.

## Domain Services

### `App\Services\SyncService`

The orchestration engine for P2P data replication.

| Method | Responsibility |
|--------|--------------|
| `syncAll()` | Pushes pending records to all known peer nodes. |
| `syncToPeer(Node $peer)` | Sends all pending `SyncStatus` rows to one peer via HTTP POST to `/api/sync/receive`. |
| `receiveRecord(...)` | Upserts an incoming record locally, using `forceFill()` + `saveQuietly()` to avoid re-triggering sync broadcasts. Handles nested `qty_types`/`discounts` for Products and `transaction_items` for Transactions. |
| `seedPendingForPeer(Node $peer)` | After a new node joins, marks all local records as pending for that peer so the full catalog gets pushed. |

## Business Workflows

### POS Checkout

1. Cashier scans a barcode, enters an item code, or searches by name/category/supplier.
2. System resolves to a `QtyType`; if unique, it auto-adds to the cart.
3. Cart stores quantity, effective price (with discount), and product/qty type names.
4. Cashier selects payment method (`cash`, `card`, `qr`) and checks out.
5. A `Transaction` and its `TransactionItem`s are created; the `Syncable` trait broadcasts changes to peers.

### Peer Discovery & Sync

1. A new node calls `POST /api/sync/join` with the workgroup token and its own identity.
2. The receiving node registers the peer, seeds pending sync rows for all local records, and dispatches a `SnapshotPullJob` to the new node.
3. The new node pulls a full snapshot from `/api/sync/snapshot`.
4. After that, every create/update on a syncable model triggers `PushSyncJob`, which calls `SyncService::syncAll()`.
5. A scheduled sync also retries failed/pending records every minute.

## Domain Patterns

- **Trait-based domain behavior:**
  - `App\Concerns\Syncable` — encapsulates the sync-queue lifecycle (create statuses on create, mark pending on update, dispatch push job).
  - `App\Concerns\Userstampable` — automatically records `created_by`/`updated_by` for audit tracking.
- **Rich model method:** `QtyType::effectivePrice()` encapsulates discount logic directly on the entity.
- **Snapshot/value preservation:** `TransactionItem` stores denormalized product/qty-type names to preserve historical sale state.
- **Polymorphic sync queue:** `SyncStatus` uses a morph relation to track per-record, per-peer replication state.

## Known Gaps

- No dedicated enums, DTOs, value objects, repositories, or command/query buses.
- Payment method is stored as a free string (`cash`/`card`/`qr`) rather than an enum.
- Discount type is stored as a string (`fixed`/`percent`) rather than an enum.
- `stock_qty` exists on `Product` but is currently reserved/future inventory tracking; no stock-deduction logic at checkout.
- Test coverage is minimal; no domain-specific tests for sync, pricing, or checkout.
