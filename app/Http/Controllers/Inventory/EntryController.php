<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\EntryDetailResource;
use App\Http\Resources\Inventory\EntryResource;
use App\Http\Resources\Inventory\EntryWithHistoryResource;
use App\Models\Inventory\Entry;
use App\Models\Store;
use App\Services\Inventory\EntryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EntryController extends Controller
{
    public function __construct(private readonly EntryService $entryService) {}

    /**
     * List entries for a specific store. The {store_id} in the URL is the store_number
     * the frontend sends; we resolve it to the internal integer id.
     */
    public function indexByStore(Request $request, string $store_id): AnonymousResourceCollection
    {
        $realStoreId = Store::idFromNumber($store_id);
        abort_if($realStoreId === null, 404, 'Store not found.');

        $filters = $request->validate([
            'date_from'    => ['nullable', 'date'],
            'date_to'      => ['nullable', 'date', 'after_or_equal:date_from'],
            'type'         => ['nullable', 'in:daily,weekly,period'],
            'submitted_by' => ['nullable', 'string', 'max:255'],
            'edited'       => ['nullable', 'boolean'],
        ]);

        $perPage = min((int) $request->query('per_page', 50), 200);

        return EntryResource::collection($this->entryService->getAll($realStoreId, $perPage, $filters));
    }

    /**
     * Entry detail WITHOUT edit history.
     * The Auth Service decides who has permission to hit this route.
     */
    public function show(Entry $entry): EntryDetailResource
    {
        return new EntryDetailResource($this->entryService->getOne($entry));
    }

    /**
     * Entry detail WITH the full append-only edit history on each item.
     * The Auth Service decides who has permission to hit this route.
     */
    public function showWithHistory(Entry $entry): EntryWithHistoryResource
    {
        return new EntryWithHistoryResource($this->entryService->getOneWithHistory($entry));
    }
}
