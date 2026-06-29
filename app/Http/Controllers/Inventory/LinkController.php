<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreLinkRequest;
use App\Http\Resources\Inventory\LinkResource;
use App\Models\Inventory\InventoryLink;
use App\Services\Inventory\LinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LinkController extends Controller
{
    public function __construct(private readonly LinkService $linkService) {}

    /** List links for a specific store — auth service validates the store_id in the path. */
    public function indexByStore(Request $request, string $store_id): AnonymousResourceCollection
    {
        $perPage = min((int) $request->query('per_page', 50), 200);

        return LinkResource::collection(
            InventoryLink::with(['store', 'creator', 'employee'])
                ->withCount('items')
                ->where('store_id', $store_id)
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
