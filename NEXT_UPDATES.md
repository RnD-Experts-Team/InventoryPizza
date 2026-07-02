# Next round of changes

Covers three things you asked about:
1. The two files the frontend teammate updated (Entry controller + resource).
2. Where we should add **filters** to list endpoints.
3. Cleaning up unused columns/tables in the **users** area.

---

## 1. The two files from the frontend teammate

### What they changed

**`EntryController::show()`** — no more role check:
```php
// BEFORE
public function show(Entry $entry, Request $request): EntryDetailResource
{
    $roles = (array) $request->attributes->get('authz_roles', []);
    $withEditHistory = in_array('inventory_specialist', $roles, true);
    return new EntryDetailResource($this->entryService->getOne($entry, $withEditHistory));
}

// AFTER  (always includes history)
public function show(Entry $entry): EntryDetailResource
{
    return new EntryDetailResource($this->entryService->getOne($entry, true));
}
```

**`EntryDetailResource::toArray()`** — no more `isSpecialist` branch. `is_edited` and `edits` are always present on every item.

### What this means

- **Every user who can open an entry now sees the full edit history**, not just `inventory_specialist`.
- The "hide edits from store managers" business rule is dropped.
- The old resource used `array_merge(..., $isSpecialist ? [...] : [])`. The new one always merges history in.

### Is this OK?

Yes — as long as the business is fine with **store managers seeing edit history for their store's entries**. Access to *which* entries a user can view is still enforced by the Auth Service (via `store_context` in the middleware). This change only affects the *shape* of the JSON, not who can reach it.

If the manager is fine with it → **no more work needed for these two files**. `EntryService::getOne($entry, true)` already loads `items.edits.editor`, so the data is there.

Small side-effect worth noting for the frontend: `is_edited` and `edits` are now **guaranteed** fields on every entry item (empty array if never edited). Their code can stop checking for their presence.

### One tidy-up (optional)

Since edits are always loaded now, the `$withEditHistory` flag in `EntryService::getOne()` is dead — it's always `true`. You can either leave it, or simplify:

```php
public function getOne(Entry $entry): Entry
{
    return $entry->load([
        'store',
        'items.item.unit1', 'items.item.unit2', 'items.item.unit3',
        'items.edits.editor',
    ])->loadCount([
        'items',
        'items as edited_items_count' => fn ($q) => $q->where('is_edited', true),
    ]);
}
```
And drop the second argument from the call in `EntryController::show()`.

---

## 2. Filters — do we need them?

**Short answer:** yes, on the two list endpoints. Not urgent for units, no.

Why: right now `stores/{store_id}/entries` and `stores/{store_id}/links` return *everything* for the store. As soon as a store has more than a few dozen entries, the frontend has no way to narrow the view — pagination alone means the user clicks through pages to find one date.

### 2a. `GET /inventory/stores/{store_id}/entries`
Suggested filters (all optional query params):

| Param | Type | Effect |
|-------|------|--------|
| `date_from` | date `YYYY-MM-DD` | `entries.date >= …` |
| `date_to` | date `YYYY-MM-DD` | `entries.date <= …` |
| `type` | `daily` \| `weekly` \| `period` | exact match |
| `submitted_by` | string | LIKE match on employee name snapshot |
| `edited` | `true` \| `false` | only entries with edited items (or without) |

Implementation goes in `EntryService::getAll()`:
```php
public function getAll(?int $storeId = null, int $perPage = 50, array $filters = []): LengthAwarePaginator
{
    $query = Entry::with('store')
        ->withCount(['items', 'items as edited_items_count' => fn ($q) => $q->where('is_edited', true)])
        ->latest();

    if ($storeId !== null) $query->where('store_id', $storeId);
    if (!empty($filters['date_from'])) $query->where('date', '>=', $filters['date_from']);
    if (!empty($filters['date_to']))   $query->where('date', '<=', $filters['date_to']);
    if (!empty($filters['type']))      $query->where('type', $filters['type']);
    if (!empty($filters['submitted_by'])) $query->where('submitted_by', 'like', '%'.$filters['submitted_by'].'%');
    if (isset($filters['edited'])) {
        $filters['edited']
            ? $query->has('items', '>', 0, 'and', fn ($q) => $q->where('is_edited', true))
            : $query->doesntHave('items', 'and', fn ($q) => $q->where('is_edited', true));
    }

    return $query->paginate($perPage);
}
```
And in `EntryController::indexByStore()` collect them:
```php
$filters = $request->only(['date_from', 'date_to', 'type', 'submitted_by', 'edited']);
return EntryResource::collection($this->entryService->getAll($realStoreId, $perPage, $filters));
```
Validate in a tiny FormRequest (`ListEntriesRequest`) or with inline `$request->validate([...])`.

### 2b. `GET /inventory/stores/{store_id}/links`
Suggested filters:

| Param | Type | Effect |
|-------|------|--------|
| `date_from` / `date_to` | date | `date` range |
| `type` | enum | `daily` / `weekly` / `period` |
| `status` | `active` \| `submitted` | exact match |
| `employee_id` | int | one employee's links only |

Same pattern in `LinkController::indexByStore()`.

### 2c. `GET /inventory/items`
Optional but nice for the item picker screen:

| Param | Type | Effect |
|-------|------|--------|
| `search` | string | LIKE on `name_en`/`name_ar`/`name_es`/`ultimatrix_id` |
| `type` | enum | items whose `types` JSON contains that value |
| `store_id` | store_number | items available for that store (`all_stores` OR pivot match) |

### 2d. Units
No filter needed — the list is small and never grows fast.

### One small guarantee

For every filter, keep the rule **"empty = no filter"** — if the frontend omits or sends an empty string, ignore it. Never treat `""` as "match empty string."

---

## 3. User table cleanup — drop unused columns

You are right that `email_verified_at`, `password`, `remember_token` are **dead fields** here:

- **Users don't log in locally** — the Auth Service verifies every request. No password check ever happens.
- **No email verification flow** — we just replicate the record from the bus.
- **No `remember me` cookies** — this is a stateless API; no sessions.

Same reasoning kills two whole side tables the base migration created:

- **`password_reset_tokens`** — we never reset passwords here.
- **`sessions`** — we don't use session-based auth (statefulApi was removed with Sanctum).

### 3a. Migration to fix

Edit `database/migrations/0001_01_01_000000_create_users_table.php`:
```php
public function up(): void
{
    // Users are replicated from the Auth Service — no local login, no sessions.
    Schema::create('users', function (Blueprint $table) {
        $table->unsignedBigInteger('id')->primary();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamps();
    });
    // password_reset_tokens: removed (no local password reset)
    // sessions:               removed (stateless API, no session auth)
}

public function down(): void
{
    Schema::dropIfExists('users');
}
```

### 3b. `app/Models/User.php` — clean it up

```php
#[Fillable(['id', 'name', 'email'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public $incrementing = false;
    protected $keyType   = 'int';

    // no more casts() — nothing left that needs casting
}
```

Remove:
- `#[Hidden(['password', 'remember_token'])]` — nothing to hide.
- `casts()` — `email_verified_at` and `password` are gone.
- `'password'` from `#[Fillable(...)]`.

### 3c. `config/auth.php` — keep the guard but drop dead bits

- Keep the `web` guard + `users` provider — the middleware calls `Auth::login($user)` to attach the user to the request, that still needs Eloquent-backed auth.
- **Delete** the `passwords` array (password reset config) — no reset flow.
- Optional: **delete** `AUTH_PASSWORD_BROKER` env references.

### 3d. Sync handler safety

`UserCreatedHandler` already only writes `name` + `email` (no password, no verified-at) — no changes needed. Sanity check:
```php
User::query()->updateOrCreate(['id' => $id], ['name' => $name, 'email' => $email]);
```
Good.

### 3e. Two things to double-check before running the migration

1. `php artisan config:clear` afterwards — Laravel caches driver config.
2. `SESSION_DRIVER` and `CACHE_STORE` in `.env` should NOT be `database` on `sessions`/`cache` values that no longer exist. We use `database` for cache/queue on other tables (`cache`, `jobs`), and `sessions` isn't used → fine. Verify with:
   ```
   php artisan session:table  # do NOT run — we're removing the table
   ```
   If `SESSION_DRIVER=database` is set in `.env`, change it to `array` (this is a stateless API — no sessions).

---

## Summary — files to change

| # | File | What changes |
|---|------|--------------|
| 1 | `EntryController.php` | *already updated by the front teammate* — no more role check |
| 2 | `EntryDetailResource.php` | *already updated* — `edits` always in payload |
| 3 | `EntryService.php` | (optional) drop the `$withEditHistory` param; add `array $filters` to `getAll()` |
| 4 | `EntryController@indexByStore` | pass `$request->only([...filters])` to the service |
| 5 | `LinkController@indexByStore` | same filter treatment for links |
| 6 | `ItemController@index` + `ItemService::getAll` | optional item filters |
| 7 | new `ListEntriesRequest` / `ListLinksRequest` (optional) | validate filter params |
| 8 | `database/migrations/…_create_users_table.php` | drop `email_verified_at`, `password`, `remember_token`, `password_reset_tokens`, `sessions` |
| 9 | `app/Models/User.php` | drop `#[Hidden]`, drop `casts()`, drop `'password'` from Fillable |
| 10 | `config/auth.php` | delete the `passwords` array |
| 11 | `.env` | set `SESSION_DRIVER=array` if it was `database` |

## Order to apply

1. **Filters** — safe, additive, no schema impact. Do first.
2. **Entry role-check removal** — already done by the frontend guy; just merge cleanly.
3. **User table cleanup** — needs `migrate:fresh`; last, so you don't repeat it.

After all three: `php artisan config:clear && php artisan migrate:fresh && php artisan scramble:export --path=api.json`.

## Two quick decisions before coding

1. **Is the manager OK with store managers seeing edit history** for entries in their store? (Confirms change #1 is a keeper.)
2. **Which filters actually matter to the frontend right now?** — no point implementing all of them if the UI only surfaces `date_from`/`date_to` + `type`. Ask, then trim the list in section 2 to just what will get used.
