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

    /** List all inventory items with units and stores. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->query('per_page', 50), 200);

        return ItemResource::collection($this->itemService->getAll($perPage));
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

    /** Delete an inventory item. */
    public function destroy(Item $item): JsonResponse
    {
        $this->itemService->delete($item);

        return response()->json(null, 204);
    }
}
