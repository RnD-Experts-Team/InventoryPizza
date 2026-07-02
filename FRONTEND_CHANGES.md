# Frontend — What Changed & What You Need to Do

Everything the frontend needs to know about the recent inventory API changes. Nothing that isn't your concern (internal DB, config, etc.) is included here.

---

## TL;DR

1. **Entry detail is now two separate endpoints** — one without edit history, one with it. Each requires a different permission.
2. **Two new list endpoints support filters** (entries and links) — date range, type, status, etc.
3. **Store IDs**: keep sending the **store_number** as the value of the `store_id` field (same field name as before). Nothing changes in how you call the API — the value just needs to be the store_number string.
4. **User objects** are slightly smaller now (no more `email_verified_at`) — no field name changes.

---

## 1. Entry detail is now two endpoints

Previously there was one URL that changed shape based on your role. That's gone. Now there are **two URLs**, each returns a **fixed shape**. The Auth Service decides which one you're allowed to hit.

### `GET /inventory/entries/{entry}` — basic detail
- **No** `is_edited`, **no** `edits` array on items.
- Permission needed: `inventory.entries.view` *(exact name to be confirmed by the Auth team)*.
- Typical caller: **store manager**.

**Response**
```json
{
  "data": {
    "id": 5,
    "reference": "ENT-005",
    "submitted_by": "John Doe",
    "store": { "id": 1, "store_number": "123456788", "name": "Downtown" },
    "date": "2026-06-30",
    "type": "daily",
    "status": "submitted",
    "items_count": 3,
    "edited_items_count": 1,
    "submitted_at": "2026-06-30T11:00:00+00:00",
    "items": [
      {
        "id": 41,
        "item": {
          "id": 1,
          "ultimatrix_id": "UTX-10042",
          "name_en": "Tomato Sauce",
          "name_ar": "صلصة طماطم",
          "name_es": "Salsa de tomate",
          "unit_1": { "id": 1, "name": "Box" },
          "unit_2": { "id": 2, "name": "Piece" },
          "unit_2_per_unit_1": "6.0000",
          "unit_3": null,
          "unit_3_per_unit_2": null
        },
        "count_unit_1": "3",
        "count_unit_2": "2",
        "count_unit_3": "0",
        "total_in_unit_1": "3.3333"
      }
    ]
  }
}
```

### `GET /inventory/entries/{entry}/history` — full detail with edits
- Same shape **plus** `is_edited` and the full `edits[]` array on each item.
- Permission needed: `inventory.entries.view_history` *(name TBD)*.
- Typical caller: **inventory specialist**.

**Response** — same as above, with each item extended:
```json
{
  "id": 41,
  "item": { /* same */ },
  "count_unit_1": "3", "count_unit_2": "2", "count_unit_3": "0",
  "total_in_unit_1": "3.3333",
  "is_edited": true,
  "edits": [
    {
      "id": 9,
      "prev_count_unit_1": "2", "prev_count_unit_2": "0", "prev_count_unit_3": "0", "prev_total": "2.0000",
      "new_count_unit_1":  "3", "new_count_unit_2":  "2", "new_count_unit_3":  "0", "new_total":  "3.3333",
      "reason": "Recount after review",
      "edited_by": { "id": 3, "name": "Manager Name" },
      "edited_at": "2026-06-30T11:30:00+00:00"
    }
  ]
}
```

### How to choose which URL to call
- If your UI needs edit history → hit `/history`.
- If it just shows the current counts → hit the plain endpoint.
- If you don't know the user's permission at call time, hit `/history` first; on `403`, fall back to the basic URL. Simpler: the Auth Service can return the user's permissions to your app at login, and you use those to decide.

Every URL has one fixed shape now — you don't need to check whether `edits` is present.

---

## 2. New filters on list endpoints (all optional query params)

### `GET /inventory/stores/{store_number}/entries`

| Param | Type | What it does |
|-------|------|--------------|
| `date_from` | date `YYYY-MM-DD` | entries with `date >=` this |
| `date_to` | date `YYYY-MM-DD` | entries with `date <=` this (must be ≥ `date_from`) |
| `type` | `daily` \| `weekly` \| `period` | exact match |
| `submitted_by` | string | LIKE search on the submitter's name snapshot |
| `edited` | `true` \| `false` | only entries that have any edited item (true) or none (false) |
| `page` | int | pagination page number |
| `per_page` | int (max 200, default 50) | rows per page |

Example:
```
GET /inventory/stores/123456788/entries?date_from=2026-06-01&date_to=2026-06-30&type=daily&edited=true
```

### `GET /inventory/stores/{store_number}/links`

| Param | Type | What it does |
|-------|------|--------------|
| `date_from` | date | |
| `date_to` | date | |
| `type` | `daily` \| `weekly` \| `period` | |
| `status` | `active` \| `submitted` | |
| `employee_id` | int | one employee's links only |
| `page`, `per_page` | int | pagination |

Example:
```
GET /inventory/stores/123456788/links?status=active&type=daily&employee_id=6
```

### Rules for all filters
- **Empty or missing** = no filter (never treated as "match empty string").
- **Invalid input** = `422` with the field name in the `errors` object.
- Multiple filters combine with **AND**.

### Response shape (unchanged — still paginated)
```json
{
  "data": [ /* array of records */ ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 20,
    "total": 47,
    "from": 1,
    "to": 20
  }
}
```

---

## 3. Store IDs — the "store_number in the store_id field" pattern

**Nothing new to do if you already do this.** Just a reminder of how it works:

- Everywhere the API asks for a store — request bodies (`store_id`, `store_ids[]`) and URLs (`/stores/{store_id}/…`) — put the **store_number** (a string like `"123456788"`) as the value.
- The backend resolves it to the internal id on its own. **You never see or send that internal id.**
- Responses include both fields in the store object:
  ```json
  "store": { "id": 1, "store_number": "123456788", "name": "Downtown" }
  ```
- `id` is the backend's internal number — don't send it back. Always round-trip using `store_number`.

### Errors
- Unknown store_number in a body → `422` (`errors.store_id`).
- Unknown store_number in a URL path → `404` (`{ "message": "Store not found." }`).

---

## 4. User objects — cleanup

The user object no longer includes `email_verified_at` (we don't verify emails locally). Everywhere a user appears now returns just:
```json
{ "id": 3, "name": "Manager Name" }
```
or in fuller places:
```json
{ "id": 3, "name": "Manager Name", "email": "manager@example.com" }
```

There's no API where a password or remember_token was ever exposed, so nothing to update in your code — just a heads-up that the user object is a bit smaller now.

---

## 5. Complete endpoint list (current)

Everything still needs a Bearer token unless marked "no auth":

| Method | Path | Notes |
|--------|------|-------|
| GET | `/inventory/units` | paginated |
| GET | `/inventory/units/{unit}` | |
| POST | `/inventory/units` | body: `{ name }` |
| PUT | `/inventory/units/{unit}` | body: `{ name }` |
| DELETE | `/inventory/units/{unit}` | 422 if referenced |
| GET | `/inventory/items` | paginated |
| GET | `/inventory/items/{item}` | |
| POST | `/inventory/items` | multipart; `store_ids[]` = store_numbers |
| PUT | `/inventory/items/{item}` | multipart (`_method=PUT`); `store_ids[]` = store_numbers |
| DELETE | `/inventory/items/{item}` | |
| POST | `/inventory/links` | `store_id` = store_number, `employee_ids[]`; returns an **array** (one per employee) |
| GET | `/inventory/links/{link}` | |
| GET | `/inventory/stores/{store_number}/links` | **new filters** (see §2) |
| GET | `/inventory/stores/{store_number}/entries` | **new filters** (see §2) |
| **GET** | **`/inventory/entries/{entry}`** | **basic detail — new/split** (§1) |
| **GET** | **`/inventory/entries/{entry}/history`** | **detail with edit history — new** (§1) |
| PATCH | `/inventory/entry-items/{entryItem}` | recount + `reason` (5–1000 chars) |
| GET | `/public/inventory/{token}` | **no auth** |
| POST | `/public/inventory/{token}/submit` | **no auth** |

---

## 6. What you need to do

- [ ] On the "view entry" screen, decide which of the two URLs (`/entries/{id}` or `/entries/{id}/history`) to call based on the logged-in user's permissions. Frontend can rely on 403 to fall back if permissions aren't known ahead of time.
- [ ] Stop checking `if (edits in response)` — the response shape is now fixed per URL.
- [ ] Add filter controls to the entries and links screens if the UX needs them (date range, type, status, edited).
- [ ] Send the **store_number** (not the numeric id) everywhere the API expects `store_id` — no code change if you already do this.
- [ ] Nothing to update for user objects unless your UI displays `email_verified_at` (it no longer exists).

---

## 7. Reference docs

If you want more detail on any of these:

- **`API_ENDPOINTS.md`** — the full endpoint reference (request/response for every route).
- **`api.json`** — the OpenAPI spec, auto-generated from the code (import into Postman/Insomnia).
- **`inventory-api.postman_collection.json`** — ready-to-use Postman collection with variables.
