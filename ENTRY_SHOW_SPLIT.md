# Task 1 — Split entry "show" into two endpoints (permission-driven)

## The idea

Right now there is **one** endpoint for viewing an entry, and the code used to look at the role to decide whether to include edit history. That's the wrong place to make the decision. Roles/permissions belong to the **Auth Service (pizzasys)**, not to this app.

The fix is to make **two different endpoints**, each returning a different shape. The Auth Service is then configured (via `authz-rules`) to say **which permission each endpoint needs**. Our backend does **zero** permission logic — it just declares the routes and returns the data.

```
Store manager  →  GET /inventory/entries/{entry}            (no edit history)
Specialist     →  GET /inventory/entries/{entry}/history    (with edit history)
```

The Auth Service grants each permission to whichever user it wants. If a user without the right permission hits an endpoint, the middleware already returns **403** — no code change needed here.

---

## Why this is better than the old approach

| Old (role check in code) | New (two endpoints, permissions) |
|---|---|
| Backend hardcoded `if (role === 'inventory_specialist')` | Backend has no role/permission logic |
| Adding another role means editing PHP | Adding another permission is done in the Auth Service, no deploy here |
| Same URL returns different shapes depending on token | Each URL always returns the same shape — clean contract |
| Frontend has to guess whether `edits` is present | Frontend picks the URL based on what it needs |

---

## Endpoint design

### `GET /inventory/entries/{entry}` — basic detail (store manager)
Returns the entry + its items with counts, but **no** `is_edited` and **no** `edits` array.

### `GET /inventory/entries/{entry}/history` — full detail (inventory specialist)
Same as above, **plus** `is_edited` and the full `edits` history on each item.

Both endpoints:
- Use the same route model binding (`{entry}`).
- Are protected by `auth.token.store` middleware, so the Auth Service authorizes per-permission.
- Frontend calls whichever one it has permission for. If it hits `/history` without the permission → 403.

---

## Files to change

### 1. `routes/api.php`
Add the second route right below the existing one:
```php
// entries
Route::get('entries/{entry}',         [EntryController::class, 'show'])
    ->name('inventory.entries.show');
Route::get('entries/{entry}/history', [EntryController::class, 'showWithHistory'])
    ->name('inventory.entries.show.history');
```

### 2. `app/Http/Controllers/Inventory/EntryController.php`
Two clean methods, each returning its own resource. No role check, no flag — just data.
```php
/** Entry detail without edit history. */
public function show(Entry $entry): EntryDetailResource
{
    return new EntryDetailResource($this->entryService->getOne($entry));
}

/** Entry detail with the full append-only edit history on each item. */
public function showWithHistory(Entry $entry): EntryWithHistoryResource
{
    return new EntryWithHistoryResource($this->entryService->getOneWithHistory($entry));
}
```
Add `use App\Http\Resources\Inventory\EntryWithHistoryResource;` at the top.

### 3. `app/Services/Inventory/EntryService.php`
Split the loader in two so each endpoint only queries what it needs:
```php
public function getOne(Entry $entry): Entry
{
    return $entry->load([
        'store',
        'items.item.unit1',
        'items.item.unit2',
        'items.item.unit3',
    ])->loadCount([
        'items',
        'items as edited_items_count' => fn ($q) => $q->where('is_edited', true),
    ]);
}

public function getOneWithHistory(Entry $entry): Entry
{
    return $entry->load([
        'store',
        'items.item.unit1',
        'items.item.unit2',
        'items.item.unit3',
        'items.edits.editor',   // extra
    ])->loadCount([
        'items',
        'items as edited_items_count' => fn ($q) => $q->where('is_edited', true),
    ]);
}
```
> Small win: the basic endpoint no longer does the `items.edits.editor` join, so it's a bit faster for the store-manager view.

### 4. Resources — one for each shape

**`app/Http/Resources/Inventory/EntryDetailResource.php`** *(rewrite to the basic shape — no edits)*
```php
class EntryDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items', fn () => $this->items->map(fn ($ei) => [
            'id'   => $ei->id,
            'item' => [
                'id'                => $ei->item->id,
                'ultimatrix_id'     => $ei->item->ultimatrix_id,
                'name_en'           => $ei->item->name_en,
                'name_ar'           => $ei->item->name_ar,
                'name_es'           => $ei->item->name_es,
                'unit_1'            => ['id' => $ei->item->unit1->id, 'name' => $ei->item->unit1->name],
                'unit_2'            => ['id' => $ei->item->unit2->id, 'name' => $ei->item->unit2->name],
                'unit_2_per_unit_1' => $ei->item->unit_2_per_unit_1,
                'unit_3'            => $ei->item->unit3
                    ? ['id' => $ei->item->unit3->id, 'name' => $ei->item->unit3->name]
                    : null,
                'unit_3_per_unit_2' => $ei->item->unit_3_per_unit_2,
            ],
            'count_unit_1'    => $ei->count_unit_1,
            'count_unit_2'    => $ei->count_unit_2,
            'count_unit_3'    => $ei->count_unit_3,
            'total_in_unit_1' => $ei->total_in_unit_1,
        ]));

        return array_merge((new EntryResource($this->resource))->toArray($request), [
            'items' => $items,
        ]);
    }
}
```

**`app/Http/Resources/Inventory/EntryWithHistoryResource.php`** *(new file — extends the basic and adds edits)*
```php
<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntryWithHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items', fn () => $this->items->map(fn ($ei) => [
            'id'   => $ei->id,
            'item' => [
                'id'                => $ei->item->id,
                'ultimatrix_id'     => $ei->item->ultimatrix_id,
                'name_en'           => $ei->item->name_en,
                'name_ar'           => $ei->item->name_ar,
                'name_es'           => $ei->item->name_es,
                'unit_1'            => ['id' => $ei->item->unit1->id, 'name' => $ei->item->unit1->name],
                'unit_2'            => ['id' => $ei->item->unit2->id, 'name' => $ei->item->unit2->name],
                'unit_2_per_unit_1' => $ei->item->unit_2_per_unit_1,
                'unit_3'            => $ei->item->unit3
                    ? ['id' => $ei->item->unit3->id, 'name' => $ei->item->unit3->name]
                    : null,
                'unit_3_per_unit_2' => $ei->item->unit_3_per_unit_2,
            ],
            'count_unit_1'    => $ei->count_unit_1,
            'count_unit_2'    => $ei->count_unit_2,
            'count_unit_3'    => $ei->count_unit_3,
            'total_in_unit_1' => $ei->total_in_unit_1,
            'is_edited'       => $ei->is_edited,
            'edits'           => EntryItemEditResource::collection($ei->edits),
        ]));

        return array_merge((new EntryResource($this->resource))->toArray($request), [
            'items' => $items,
        ]);
    }
}
```
There's duplication between the two resources. If it bothers you, extract the item-mapping into a private helper on `EntryDetailResource` and have `EntryWithHistoryResource` extend it and merge `is_edited` + `edits` on top. Not required — duplication here is 10 lines and easy to read.

### 5. No middleware / no controller permission check
**Do not** add `if (in_array('...', $roles, true))` anywhere. If someone without the right permission hits the endpoint, the `auth.token.store` middleware already returns **403** from the Auth Service's `authorized: false` response. That's the whole point of this refactor.

---

## The Auth Service side (pizzasys) — what needs to be configured

This is where the **permission** rules live. You add two `authz-rules` entries (or whatever the Auth Service calls them), one per route:

| Route name | HTTP + path | Permission required |
|---|---|---|
| `inventory.entries.show` | `GET /inventory/entries/{entry}` | e.g. `inventory.entries.view` |
| `inventory.entries.show.history` | `GET /inventory/entries/{entry}/history` | e.g. `inventory.entries.view_history` |

Then assign permissions to users/roles in the Auth Service however that project already does it:
- Store managers → `inventory.entries.view`.
- Inventory specialists → `inventory.entries.view` **and** `inventory.entries.view_history`.

Any adjustment later (e.g. "let X user also see history") is a **data change in the Auth Service** — no PHP change here.

---

## Frontend impact

Very small:
- On the "view entry" screen the frontend already handles both shapes (it was checking whether `edits` exists). It just needs to know **which URL to call**:
  - Store manager UI → `/inventory/entries/{id}`
  - Specialist UI → `/inventory/entries/{id}/history`

If the frontend has a single "view entry" button and wants to decide dynamically:
- Simplest: try `/history` first; on 403, fall back to `/`.
- Or: the Auth Service can return the user's permissions to the frontend at login; the frontend then picks based on whether the user has `.view_history`.

Either way, no shape guessing — a URL always returns the same JSON.

---

## Testing (quick sanity check)

Once wired up:

1. Token with `.view` but **not** `.view_history`:
   - `GET /inventory/entries/1` → **200** with items, no `edits`, no `is_edited`.
   - `GET /inventory/entries/1/history` → **403** (Auth Service refuses).

2. Token with both permissions:
   - `GET /inventory/entries/1` → **200**, basic shape (no `edits`).
   - `GET /inventory/entries/1/history` → **200**, includes `is_edited` and `edits[]` per item.

3. Unknown entry id on either endpoint → **404** (route model binding).

---

## Summary — files to touch

| # | File | Change |
|---|------|--------|
| 1 | `routes/api.php` | add `entries/{entry}/history` route |
| 2 | `EntryController.php` | add `showWithHistory()`; keep `show()` clean (no role check) |
| 3 | `EntryService.php` | split into `getOne()` (basic) and `getOneWithHistory()` |
| 4 | `EntryDetailResource.php` | trim to the basic shape (no `is_edited`, no `edits`) |
| 5 | `EntryWithHistoryResource.php` *(new)* | full shape (basic + `is_edited` + `edits`) |
| — | Auth Service (pizzasys) | register the 2 permissions and map them to the 2 route names |

Then: `php artisan scramble:export --path=api.json` so the OpenAPI spec shows both endpoints.

---

## Two decisions before coding

1. **Endpoint name for the "with history" one** — `/history` (my suggestion) or `/full` or `/with-edits`?
2. **Permission names** — what naming convention does the Auth Service already use? (`inventory.entries.view` / `inventory.entries.view_history` follows a common dot-namespaced pattern; match what pizzasys uses so the operators aren't surprised.)
