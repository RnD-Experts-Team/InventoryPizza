<?php

use App\Http\Controllers\Inventory\CountsController;
use App\Http\Controllers\Inventory\EntryController;
use App\Http\Controllers\Inventory\EntryItemController;
use App\Http\Controllers\Inventory\ItemController;
use App\Http\Controllers\Inventory\LinkController;
use App\Http\Controllers\Inventory\PublicInventoryController;
use App\Http\Controllers\Inventory\UnitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes (no auth) — public inventory submission links
|--------------------------------------------------------------------------
*/
Route::prefix('public/inventory')->group(function () {
    Route::get('{token}', [PublicInventoryController::class, 'show'])
        ->name('public.inventory.show');
    Route::post('{token}/submit', [PublicInventoryController::class, 'submit'])
        ->name('public.inventory.submit');
});

/*
|--------------------------------------------------------------------------
| Protected inventory routes
|--------------------------------------------------------------------------
| Authentication AND authorization are centralized in the Auth Service
| (pizzasys). The `auth.token.store` middleware verifies the user's token and
| asks pizzasys whether the user may access this route (via auth-rules).
| There is no local login or local role check anymore.
*/
Route::prefix('inventory')
    ->middleware('auth.token.store')
    ->group(function () {

        // ── Units ────────────────────────────────────────────────────────────
        Route::get('units',           [UnitController::class, 'index'])->name('inventory.units.index');
        Route::get('units/{unit}',    [UnitController::class, 'show'])->name('inventory.units.show');
        Route::post('units',          [UnitController::class, 'store'])->name('inventory.units.store');
        Route::put('units/{unit}',    [UnitController::class, 'update'])->name('inventory.units.update');
        Route::delete('units/{unit}', [UnitController::class, 'destroy'])->name('inventory.units.destroy');

        // ── Items ────────────────────────────────────────────────────────────
        // Tags are managed inline through these endpoints — there is no separate
        // tags CRUD. The manager sends tag names on create/update; unknown names
        // become new tags, matching existing names reuse the existing tag.
        Route::get('items',           [ItemController::class, 'index'])->name('inventory.items.index');
        Route::post('items',          [ItemController::class, 'store'])->name('inventory.items.store');
        Route::get('items/{item}',    [ItemController::class, 'show'])->name('inventory.items.show');
        Route::put('items/{item}',    [ItemController::class, 'update'])->name('inventory.items.update');
        Route::patch('items/{item}/active', [ItemController::class, 'setActive'])->name('inventory.items.set-active');
        Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('inventory.items.destroy');

        // ── Links ────────────────────────────────────────────────────────────
        Route::post('links',       [LinkController::class, 'store'])->name('inventory.links.store');
        Route::get('links/{link}', [LinkController::class, 'show'])->name('inventory.links.show');

        // ── Entries ──────────────────────────────────────────────────────────
        // Two shapes, two permissions — the Auth Service decides who can hit which.
        Route::get('entries/{entry}',         [EntryController::class, 'show'])
            ->name('inventory.entries.show');
        Route::get('entries/{entry}/history', [EntryController::class, 'showWithHistory'])
            ->name('inventory.entries.show.history');

        // ── Entry Items ──────────────────────────────────────────────────────
        Route::patch('entry-items/{entryItem}', [EntryItemController::class, 'update'])->name('inventory.entry-items.update');

        // ── Store-scoped routes ───────────────────────────────────────────────
        // Counted quantities for one store: a date range and selected items in a
        // single query. The entry endpoints cannot answer that — the list returns
        // headers with no quantities, and the detail is keyed by one entry id — so
        // a week of three items cost 8 calls, each with its own synchronous token
        // check against pizzasys. This is one.
        //
        // The store is in the PATH as {store_id}, not a query list, so pizzasys
        // authorizes it the same way it authorizes every other store-scoped route
        // here: auth_rules reads store_id_sources.path and decides. Nothing in this
        // service scopes stores locally, so that decision has to be reachable.
        Route::get('stores/{store_id}/counts', [CountsController::class, 'index'])
            ->name('inventory.store.counts.index');

        // The same answer for many stores in one request — what the weekly grid
        // needs, where the per-store route costs 44 calls and 44 token checks.
        //
        // No store in the path and none in the query: it returns every active
        // store, and pizzasys decides whether the caller is someone who may see
        // them all. Its resolver has a mode for precisely that —
        //   store_scope_mode: "all_stores"
        //       "user must have access to every active store, then check global perms"
        // So the specialist's grid passes, and a store manager gets 403 and uses
        // the per-store route above, which is the one built for them.
        Route::get('counts', [CountsController::class, 'bulk'])
            ->name('inventory.counts.index');

        Route::get('stores/{store_id}/links',     [LinkController::class, 'indexByStore'])->name('inventory.store.links.index');
        Route::get('stores/{store_id}/entries',   [EntryController::class, 'indexByStore'])->name('inventory.store.entries.index');
    });
