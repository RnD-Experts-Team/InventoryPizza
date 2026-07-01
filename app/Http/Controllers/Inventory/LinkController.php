<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreLinkRequest;
use App\Http\Resources\Inventory\LinkResource;
use App\Models\Inventory\InventoryLink;
use App\Models\Store;
use App\Services\Inventory\LinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LinkController extends Controller
{
    public function __construct(private readonly LinkService $linkService) {}

    /**
     * List links for a specific store. The {store_id} in the URL is the store_number
     * the frontend sends; we resolve it to the internal integer id.
     */
    public function indexByStore(Request $request, string $store_id): AnonymousResourceCollection
    {
        $realStoreId = Store::idFromNumber($store_id);
        abort_if($realStoreId === null, 404, 'Store not found.');

        $perPage = min((int) $request->query('per_page', 50), 200);

        return LinkResource::collection(
            InventoryLink::with(['store', 'creator', 'employee'])
                ->withCount('items')
                ->where('store_id', $realStoreId)
                ->latest()
                ->paginate($perPage)
        );
    }

    /**
     * Generate one inventory link per selected store user.
     * Returns the list of created links (each with its own token).
     */
    public function store(StoreLinkRequest $request): JsonResponse
    {
        $links = $this->linkService->generate($request->validated(), $request->user());

        return LinkResource::collection($links)->response()->setStatusCode(201);
    }

    /** Get a single inventory link by ID. */
    public function show(Request $request, InventoryLink $link): LinkResource
    {
        return new LinkResource($link->load(['store', 'creator', 'employee'])->loadCount('items'));
    }
}
