# BetterPos — Architecture Overview

## Stack

- **Laravel 13** · **Filament 5** (Livewire 4, Alpine.js, Tailwind CSS v4) · **MySQL**
- Each machine runs its own instance. No central server.

---

## Panels

| Panel | URL | Purpose |
|---|---|---|
| Admin | `/admin` | Back-office: products, catalog, transactions, sync |
| Cashier | `/cashier` | POS terminal for operators |

Both panels share the same `User` model. Login accepts **username or email**.

---

## Database Schema

```
nodes               — peer machines (UUID PK, is_self, url, last_seen_at)
users               — name, username, email, password

categories          — UUID PK, name, is_active + userstamps + syncable
suppliers           — UUID PK, name, address + userstamps + syncable
locations           — UUID PK, name + userstamps + syncable

products            — UUID PK, item_code (UNQ), name
                      → category_id, supplier_id, location_id
                      → stock_qty (reserved; future movement tracking)
                      + userstamps + syncable

qty_types           — UUID PK, product_id
                      name, qty (int), barcode (UNQ)
                      base_price, selling_price
                      is_base (bool — exactly one per product, qty=1)

discounts           — id, qty_type_id (UNQ)
                      enabled, type (fixed|percent), value
                      timed, date_start, date_end

transactions        — UUID PK, origin_node_id, cashier_name
                      total_amount, payment_method + syncable

transaction_items   — UUID PK, transaction_id
                      product_id, product_name (snapshot)
                      qty_type_id, qty_type_name (snapshot)
                      unit_price, qty, subtotal

sync_statuses       — (syncable_type, syncable_id, node_id) UNQ
                      status: pending → synced | failed
```

---

## Catalog Logic

- **QtyType** is the atomic unit of sale — same product can have multiple units (e.g. Cup, Box-6, Carton)
- `QtyType::effectivePrice()` — returns `selling_price` minus discount if enabled (and within timed window)
- `base_price` = purchase/reference cost; never shown at checkout
- Each product must have exactly one QtyType with `is_base=true` and `qty=1`

---

## POS Terminal (`/cashier/pos-terminal`)

Single Livewire page. Nothing loads on open — entirely search-driven.

**Search resolution on Enter (scanner-friendly):**
1. Exact `barcode` sole match → add to cart, clear field
2. Exact `item_code` sole match → add base qty type to cart, clear field
3. No sole match → show fuzzy results (name / item_code / category / supplier name)

**Fuzzy typing** (debounced 300ms): shows qty type cards with effective price and strikethrough if discounted. Category filter pills appear during fuzzy mode.

Cart is keyed by `qty_type_id`. Checkout creates `Transaction` + `TransactionItem` rows with name snapshots.

---

## P2P Sync

- `Syncable` trait (on `Category`, `Product`, `Supplier`, `Location`, `Transaction`) auto-creates a `sync_statuses` row per known peer on every record creation
- `SyncService::syncAll()` pushes all pending records to all peers
- `POST /api/sync/receive` — inbound from peers; conflict resolution: last-write-wins by `updated_at`
- `POST /api/sync/push` — trigger outbound sync manually

---

## Admin Resources

| Resource | Nav Group | Notes |
|---|---|---|
| Products | Catalog | Includes QtyTypes relation manager with inline discount |
| Categories | Catalog | |
| Suppliers | Catalog | |
| Locations | Catalog | |
| Transactions | Sales | Read-only; view shows full line items |
| Nodes | — | Peer machine registry |
| Sync Statuses | — | Read-only per-record sync monitor |

---

## Key Concerns (Traits)

| Trait | File | What it does |
|---|---|---|
| `Syncable` | `app/Concerns/Syncable.php` | Auto-registers `sync_statuses` rows on creation |
| `Userstampable` | `app/Concerns/Userstampable.php` | Auto-stamps `created_by` / `updated_by` from auth user |
