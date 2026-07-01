<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\EntryDetailResource;
use App\Http\Resources\Inventory\EntryResource;
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

        $perPage = min((int) $request->query('per_page', 50), 200);

        return EntryResource::collection($this->entryService->getAll($realStoreId, $perPage));
    }

    /** Entry detail with items. Edit history only visible to inventory_specialist. */
    public function show(Entry $entry, Request $request): EntryDetailResource
    {
        $roles = (array) $request->attributes->get('authz_roles', []);
        $withEditHistory = in_array('inventory_specialist', $roles, true);

        return new EntryDetailResource($this->entryService->getOne($entry, $withEditHistory));
    }

}
