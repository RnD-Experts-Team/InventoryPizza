<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\EntryDetailResource;
use App\Http\Resources\Inventory\EntryResource;
use App\Models\Inventory\Entry;
use App\Services\Inventory\EntryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EntryController extends Controller
{
    public function __construct(private readonly EntryService $entryService) {}

    /** List entries for a specific store — auth service validates the store_id in the path. */
    public function indexByStore(Request $request, string $store_id): AnonymousResourceCollection
    {
        $perPage = min((int) $request->query('per_page', 50), 200);

        return EntryResource::collection($this->entryService->getAll($store_id, $perPage));
    }

    /** Entry detail with items. Edit history only visible to inventory_specialist. */
    public function show(Entry $entry, Request $request): EntryDetailResource
    {
        $roles = (array) $request->attributes->get('authz_roles', []);
        $withEditHistory = in_array('inventory_specialist', $roles, true);

        return new EntryDetailResource($this->entryService->getOne($entry, $withEditHistory));
    }

}
