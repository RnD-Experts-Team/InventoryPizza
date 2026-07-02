# Inventory Project — Full Update Log

A summary of everything changed in the inventory microservice, grouped by area. Reflects the **current/final state** of the code.

---

## 1. Authentication — Sanctum fully removed

This service has **no local login**. Authentication and authorization are centralized in the external **Auth Service (pizzasys)**; tokens are verified per request.

- Deleted `config/sanctum.php`.
- Removed `$middleware->statefulApi()` from `bootstrap/app.php` (it pulled in a Sanctum middleware that no longer exists).
- Removed `HasApiTokens` from `App\Models\User`.
- Deleted the `create_personal_access_tokens_table` migration.
- Deleted `AuthController` and `LoginRequest`.
- Removed `laravel/sanctum` from Composer.
- **Auth flow:** `AuthTokenStoreScopeMiddleware` extracts the Bearer token, calls the Auth Service to verify + authorize, then exposes `authz_roles` / `authz_permissions` / `authz_ext` on the request for controllers.

---

## 2. User model & DB cleanup

- Consolidated the scattered user migrations into `create_users_table` (final schema in one place).
- **Removed the `role` column** — role/authorization comes from `authz_roles` (Auth Service), not a stale local field.
- `users.id` is externally controlled (no auto-increment); `password` is nullable (users never log in here).

---

## 3. NATS event handlers (sync from other services)

- Fixed `StoreCreatedHandler`, `StoreUpdatedHandler`, `StoreDeletedHandler`.
- Added `EmployeeCreatedHandler`, `EmployeeUpdatedHandler`, `EmployeeDeletedHandler` (+ shared `EmployeeHandlerHelpers`).
- Removed a broken reference to a non-existent `UserRoleAssignedHandler`.
- User sync handlers (`UserCreated/Updated/Deleted`) only replicate `id/name/email` — no invented fields.

---

## 4. Pagination on all list endpoints

- Every list endpoint now returns Laravel's paginated shape: `{ data:[...], links:{...}, meta:{ current_page, last_page, total, per_page, ... } }`.
- Controlled with `?page=` and `?per_page=` (max **200**, default 50).
- Applied to units, items, links-by-store, entries-by-store.

---

## 5. Store scoping & route cleanup

- **Removed** the local `store_user` pivot / `managedStores` approach — store scope belongs to the Auth Service, not this DB.
- **Removed** the global `GET /inventory/links` and `GET /inventory/entries` index routes.
- **Added** store-scoped routes:
  - `GET /inventory/stores/{store_id}/links`
  - `GET /inventory/stores/{store_id}/entries`
- The Auth Service authorizes access to the store (via `store_context` in the middleware).
- **No employees endpoint** — `StoreEmployeeController` was removed; link creation takes `employee_ids` directly.

---

## 6. Links are employee-centric

- `POST /inventory/links` body: `{ store_id, date, type, employee_ids: [...] }`.
- One link is generated **per employee**; items are **auto-selected by the server** from the store + type (the client never sends item ids).
- Race condition on public submission fixed with `lockForUpdate()` inside a transaction + a dedicated `LinkAlreadySubmittedException` (returns **410** if already submitted).

---

## 7. Store identity: integer id + store_number resolution

**7a. `stores.id` changed from string → integer.**
- `stores.id` and every `store_id` FK (`inventory_links`, `inventory_entries`, `inventory_item_store`, `employees`) are now `unsignedBigInteger`.
- `Store` model `keyType` → `int`; `store_id` casts added on `InventoryLink`, `Entry`, `Employee`.
- NATS store/employee handlers now parse ids as integers.

**7b. Frontend sends `store_number`, backend resolves the id.** *(latest change)*
- The API **field/param names did not change** — the frontend still sends `store_id` and hits the same URLs; the **value** is now the `store_number` (e.g. `"123456788"`).
- `store_number` is now **unique + not null** on `stores`.
- Added `Store::idFromNumber()` — the single resolver.
- Resolution happens in:
  - `StoreLinkRequest` (validates value against `store_number`, swaps to real id in `validated()`; employee validation uses the resolved id)
  - `LinkController@indexByStore` and `EntryController@indexByStore` (path value → real id, 404 if unknown)
  - `StoreItemRequest` / `UpdateItemRequest` (item `store_ids[]` accept store_numbers, resolved to ids)
  - `AuthTokenStoreScopeMiddleware` (sends the **resolved integer id** to the Auth Service in `store_context`)
- Responses now include `store_number` alongside `id` (additive) in `LinkResource`, `EntryResource`, `EntryDetailResource`, `PublicLinkResource`, and `ItemResource`.

> See `STORE_NUMBER_RESOLUTION.md` (design) and `STORE_NUMBER_TEST.md` (how to test).

---

## 8. Items

- Create/update use `multipart/form-data` (for the optional image).
- `name_ar` and `name_es` are **required**.
- `created_by` recorded for audit.
- `image` is returned as a full URL (or `null`).
- `PublicLinkResource` now also returns each item's `image`.

---

## 9. Entries & edit history

- Entry detail (`GET /inventory/entries/{entry}`) returns the entry + its items.
- `is_edited` and the `edits` history array are **only** included for `inventory_specialist` tokens.
- `PATCH /inventory/entry-items/{entryItem}` records every edit to an append-only history (requires a `reason`, 5–1000 chars).
- Removed the unused `pending` entry status (enum is now just `submitted`).

---

## 10. Audit columns & performance indexes

- Added nullable `created_by` (FK → users) on `inventory_items` and `inventory_units`.
- Added indexes on `employees.store_id`, `inventory_links.store_id` + `status`, `inventory_entries.store_id` + `date`.

---

## 11. Docs, tooling & test artifacts

- Moved 16 internal `.md` docs into `docs/` and git-ignored that folder.
- Rewrote `public/api-tester.html` to match the real endpoints (token paste, employee-id link creation, store-scoped lists, public form, pagination).
- Regenerated `api.json` (Scramble OpenAPI) after every contract change.
- Rewrote `inventory-api.postman_collection.json` for the frontend.
- Added `API_ENDPOINTS.md` (human-readable endpoint reference).
- Added analysis/handoff docs: `STORE_ID_EXPLAINED.md`, `STORE_ID_TO_INTEGER_CHANGES.md`, `STORE_NUMBER_RESOLUTION.md`, `STORE_NUMBER_TEST.md`.

---

## Current database migrations (18)

```
0001_01_01_000000_create_users_table
0001_01_01_000001_create_cache_table
0001_01_01_000002_create_jobs_table
2026_06_24_000002_create_stores_table               (id integer PK, store_number unique)
2026_06_24_000003_create_inventory_units_table
2026_06_24_000004_create_inventory_items_table
2026_06_24_000005_create_inventory_item_store_table (store_id integer FK)
2026_06_24_000006_create_inventory_links_table      (store_id integer FK)
2026_06_24_000007_create_inventory_link_item_table
2026_06_24_000008_create_inventory_entries_table    (store_id integer FK)
2026_06_24_000009_create_inventory_entry_items_table
2026_06_24_000010_create_inventory_entry_item_edits_table
2026_06_24_100001_create_event_inbox_table
2026_06_26_000001_create_employees_table            (store_id integer)
2026_06_26_000003_add_employee_fk_to_inventory_links
2026_06_29_000001_add_performance_indexes
2026_06_29_000002_add_created_by_to_items_and_units
2026_06_29_000003_remove_pending_from_entries_status
```

---

## Current API endpoints

| Method | Path | Notes |
|--------|------|-------|
| GET/POST | `/inventory/units` | list paginated / create |
| GET/PUT/DELETE | `/inventory/units/{unit}` | |
| GET/POST | `/inventory/items` | multipart on create |
| GET/PUT/DELETE | `/inventory/items/{item}` | multipart on update (`_method=PUT`) |
| POST | `/inventory/links` | `employee_ids[]`; returns array |
| GET | `/inventory/links/{link}` | |
| GET | `/inventory/stores/{store_id}/links` | `{store_id}` = store_number |
| GET | `/inventory/stores/{store_id}/entries` | `{store_id}` = store_number |
| GET | `/inventory/entries/{entry}` | detail + items |
| PATCH | `/inventory/entry-items/{entryItem}` | recount + reason |
| GET | `/public/inventory/{token}` | no auth |
| POST | `/public/inventory/{token}/submit` | no auth |

> Everywhere a store is referenced, the frontend sends the **store_number**; the backend resolves it to the internal integer id.

---

## Manual steps already completed

- `composer remove laravel/sanctum`
- `php artisan migrate:fresh` (rebuilt with the final schema)
- `php artisan scramble:export --path=api.json` (regenerated the spec)

> Note: the `Redis` error you saw was in **pizzasys** (a separate project), not here — it forced `Cache::store('redis')` without the phpredis extension. That fix belongs in the pizzasys repo, not this one.
