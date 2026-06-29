<?php

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
        Route::get('items',           [ItemController::class, 'index'])->name('inventory.items.index');
        Route::post('items',          [ItemController::class, 'store'])->name('inventory.items.store');
        Route::get('items/{item}',    [ItemController::class, 'show'])->name('inventory.items.show');
        Route::put('items/{item}',    [ItemController::class, 'update'])->name('inventory.items.update');
        Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('inventory.items.destroy');

        // ── Links ────────────────────────────────────────────────────────────
        Route::post('links',       [LinkController::class, 'store'])->name('inventory.links.store');
        Route::get('links/{link}', [LinkController::class, 'show'])->name('inventory.links.show');

        // ── Entries ──────────────────────────────────────────────────────────
        Route::get('entries/{entry}', [EntryController::class, 'show'])->name('inventory.entries.show');

        // ── Entry Items ──────────────────────────────────────────────────────
        Route::patch('entry-items/{entryItem}', [EntryItemController::class, 'update'])->name('inventory.entry-items.update');

        // ── Store-scoped routes ───────────────────────────────────────────────
        Route::get('stores/{store_id}/links',     [LinkController::class, 'indexByStore'])->name('inventory.store.links.index');
        Route::get('stores/{store_id}/entries',   [EntryController::class, 'indexByStore'])->name('inventory.store.entries.index');
    });
