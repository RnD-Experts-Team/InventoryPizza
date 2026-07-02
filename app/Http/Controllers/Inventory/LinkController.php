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

        $filters = $request->validate([
            'date_from'   => ['nullable', 'date'],
            'date_to'     => ['nullable', 'date', 'after_or_equal:date_from'],
            'type'        => ['nullable', 'in:daily,weekly,period'],
            'status'      => ['nullable', 'in:active,submitted'],
            'employee_id' => ['nullable', 'integer'],
        ]);

        $perPage = min((int) $request->query('per_page', 50), 200);

        $query = InventoryLink::with(['store', 'creator', 'employee'])
            ->withCount('items')
            ->where('store_id', $realStoreId)
            ->latest();

        if (! empty($filters['date_from']))   $query->whereDate('date', '>=', $filters['date_from']);
        if (! empty($filters['date_to']))     $query->whereDate('date', '<=', $filters['date_to']);
        if (! empty($filters['type']))        $query->where('type', $filters['type']);
        if (! empty($filters['status']))      $query->where('status', $filters['status']);
        if (! empty($filters['employee_id'])) $query->where('employee_id', $filters['employee_id']);

        return LinkResource::collection($query->paginate($perPage));
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
