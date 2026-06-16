# Peer2Pos — Laravel Design

> Filament panels, routing, middleware, configuration, migrations, and other framework-specific implementation details.
> For the business domain, entities, and workflows, see [ARCHITECTURE.md](ARCHITECTURE.md).

## Filament Panels

The app runs **Filament v5** with two panels registered in `bootstrap/providers.php`.

| Panel | Provider | Path | Notes |
|-------|----------|------|-------|
| **Admin** | `App\Providers\Filament\AdminPanelProvider` | `/admin` | Default panel, amber primary color. |
| **Cashier** | `App\Providers\Filament\CashierPanelProvider` | `/cashier` | Blue primary color, POS-focused. |

Both panels use a custom login page: `App\Filament\Auth\Login`, which lets users log in with either **username or email** by resolving the input to an email before Filament's standard auth check.

### Admin Resources

| Resource | Model | Navigation group | Key patterns |
|----------|-------|------------------|--------------|
| `CategoryResource` | `Category` | Catalog | Form schema with `TextInput`, `Toggle`; table counts `products`. |
| `LocationResource` | `Location` | Catalog | Single name field; counts `products`. |
| `SupplierResource` | `Supplier` | Catalog | Name + textarea address; counts `products`. |
| `ProductResource` | `Product` | Catalog | `Select` relationships with `createOptionForm`; `SelectFilter`; relation manager `QtyTypesRelationManager`. |
| `NodeResource` | `Node` | Sync | `ListNodes` has a custom header action to join a peer workgroup. |
| `SyncStatusResource` | `SyncStatus` | Sync | Read-only (`canCreate() => false`); status badge colors; morph-based listing. |
| `TransactionResource` | `Transaction` | Sales | Read-only create (`canCreate() => false`); uses `infolist()` with `RepeatableEntry` for line items; view-only pages. |

### Cashier Pages

- `App\Filament\Cashier\Pages\PosTerminal` — full custom Livewire/Filament page with a dedicated Blade view at `resources/views/filament/cashier/pages/pos-terminal.blade.php`.

### Relation Managers

- `ProductResource\RelationManagers\QtyTypesRelationManager` — manages nested `QtyType` records with embedded discount fields, using `mutateFormDataBeforeCreate/Save/Fill` hooks.

## Routing

### Web

`routes/web.php` contains only:

```php
Route::get('/', fn () => view('welcome'));
```

### API

Peer-to-peer sync API under `/api`:

| Method | URI | Controller | Middleware |
|--------|-----|------------|------------|
| POST | `/sync/join` | `JoinController@join` | public (token in body) |
| POST | `/sync/receive` | `SyncController@receive` | `workgroup` |
| POST | `/sync/push` | `SyncController@push` | `workgroup` |
| GET | `/sync/snapshot` | `SyncController@snapshot` | `workgroup` |

### Console

- Default `inspire` Artisan command.
- Scheduled closure calling `SyncService::syncAll()` every minute (`withoutOverlapping()`).

## Controllers

- `App\Http\Controllers\Controller` — base controller.
- `App\Http\Controllers\Api\JoinController` — validates join payload, verifies workgroup token, registers the peer, seeds pending sync rows, dispatches `PushSyncJob`.
- `App\Http\Controllers\Api\SyncController` — receives records, triggers manual push, returns full snapshot.

## Authentication

- Guard: default `web` session guard (`config/auth.php`).
- User model: `App\Models\User` with `username` field; uses PHP 8 attributes `#[Fillable]` / `#[Hidden]`.
- Filament login override (`App\Filament\Auth\Login`) enables username-or-email login by looking up `email` from `username`.

## Middleware

- `App\Http\Middleware\ValidateWorkgroupToken` — aliased as **`workgroup`** in `bootstrap/app.php`.
  - Compares the request Bearer token to `config('app.workgroup_token')` using `hash_equals`.
  - Returns JSON 401/500 responses for API sync routes.
- Filament panels use the standard Laravel/Filament cookie/session/CSRF/auth middleware stack.

## Validation

- No dedicated Form Request classes (`app/Http/Requests/` does not exist).
- No custom Rules (`app/Rules/` does not exist).
- No Policies (`app/Policies/` does not exist).
- Validation is done inline:
  - Controllers use `$request->validate([...])`.
  - Filament handles validation through schema components (`required`, `url`, `uuid`, `unique`, etc.).

## Providers & Service Container

- `App\Providers\AppServiceProvider` — empty (`register()` / `boot()` are no-ops).
- `App\Providers\Filament\AdminPanelProvider`
- `App\Providers\Filament\CashierPanelProvider`
- `SyncService` is resolved through constructor injection / `app(SyncService::class)`; no explicit bindings.

## Configuration

`config/app.php` adds custom keys:

| Key | Env Variable | Purpose |
|-----|--------------|---------|
| `node_id` | `NODE_ID` | UUID of the local node. |
| `node_name` | `NODE_NAME` | Human-readable terminal name. |
| `workgroup_token` | `WORKGROUP_TOKEN` | Shared secret for inter-node auth. |

`.env.example` shows SQLite default connection, database queue, database cache, and database sessions.

## Database Schema

### Standard Laravel Tables

- `users` (+ `username`), `password_reset_tokens`, `sessions`
- `cache`, `cache_locks`
- `jobs`, `job_batches`, `failed_jobs`

### Domain Tables

| Table | PK | Notable columns / constraints |
|-------|----|------------------------------|
| `nodes` | UUID | `is_self`, `last_seen_at`, `joined_at` |
| `categories` | UUID | FK `origin_node_id` → `nodes`; userstamps |
| `suppliers` | UUID | FK `origin_node_id`; userstamps |
| `locations` | UUID | FK `origin_node_id`; userstamps |
| `products` | UUID | `item_code` unique; FKs to category, supplier, location, origin node; userstamps; refactored by a later migration |
| `qty_types` | UUID | FK `product_id`; `barcode` unique; `is_base` flag |
| `discounts` | Auto-increment | FK `qty_type_id` (1:1); enabled/type/value/timed dates |
| `transactions` | UUID | FK `origin_node_id`; `payment_method`, `total_amount` |
| `transaction_items` | UUID | FKs to transaction, product, qty_type; snapshots product/qty-type names |
| `sync_statuses` | Auto-increment | Morph columns, `node_id`, status `pending\|synced\|failed`; unique triplet index |

## Jobs

| Job | Trigger | Purpose |
|-----|---------|---------|
| `PushSyncJob` | Dispatched by `Syncable` trait on create/update. | Calls `SyncService::syncAll()`. |
| `SnapshotPullJob` | Dispatched after joining a workgroup. | Pulls a full snapshot from a peer's `/api/sync/snapshot` endpoint. |

## Testing Structure

- `tests/TestCase.php` — standard Laravel base test case.
- `tests/Feature/ExampleTest.php` — checks `GET /` returns 200.
- `tests/Unit/ExampleTest.php` — trivial PHPUnit placeholder.
- `database/factories/UserFactory.php` — standard user factory (does not set `username` by default).
- `database/seeders/DatabaseSeeder.php` — seeds the self node, admin user, supplier, location, categories, products, qty types, and discounts.

## Notable Testing Gaps

- No feature tests for Filament resources or POS checkout.
- No tests for sync endpoints (`/api/sync/*`) or `SyncService`.
- No model-specific factories beyond `UserFactory`.
