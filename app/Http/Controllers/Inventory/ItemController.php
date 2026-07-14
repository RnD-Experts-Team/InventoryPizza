<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreItemRequest;
use App\Http\Requests\Inventory\UpdateItemRequest;
use App\Http\Resources\Inventory\ItemResource;
use App\Models\Inventory\Item;
use App\Services\Inventory\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ItemController extends Controller
{
    public function __construct(private readonly ItemService $itemService) {}

    /** List inventory items with units and stores. Pass ?active=true|false to filter by status. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->query('per_page', 50), 200);
        $active  = $request->has('active') ? $request->boolean('active') : null;

        return ItemResource::collection($this->itemService->getAll($perPage, $active));
    }

    /**
     * Create a new inventory item.
     * Accepts multipart/form-data when uploading an image (jpg/jpeg/png, max 2MB).
     */
    public function store(StoreItemRequest $request): JsonResponse
    {
        $item = $this->itemService->create($request->validated(), $request->file('image'), $request->user());

        return (new ItemResource($item))->response()->setStatusCode(201);
    }

    /** Get a single inventory item by ID. */
    public function show(Item $item): ItemResource
    {
        return new ItemResource($item->load(['unit1', 'unit2', 'unit3', 'stores']));
    }

    /**
     * Update an existing inventory item.
     * Pass a new image file to replace the existing one.
     */
    public function update(UpdateItemRequest $request, Item $item): ItemResource
    {
        return new ItemResource($this->itemService->update($item, $request->validated(), $request->file('image')));
    }

    /** Activate or deactivate an item. Body: { "is_active": true|false }. */
    public function setActive(Request $request, Item $item): ItemResource
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        return new ItemResource($this->itemService->setActive($item, $data['is_active']));
    }

    /**
     * Hard-delete an inventory item. Only succeeds if the item was never used
     * (no entries, no links); otherwise returns 422. For normal use, deactivate instead.
     */
    public function destroy(Item $item): JsonResponse
    {
        $this->itemService->delete($item);

        return response()->json(null, 204);
    }
}
