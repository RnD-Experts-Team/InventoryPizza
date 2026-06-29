<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreUnitRequest;
use App\Http\Requests\Inventory\UpdateUnitRequest;
use App\Http\Resources\Inventory\UnitResource;
use App\Models\Inventory\Unit;
use App\Services\Inventory\UnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController extends Controller
{
    public function __construct(private readonly UnitService $unitService) {}

    /** List all inventory units. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->query('per_page', 50), 200);

        return UnitResource::collection($this->unitService->getAll($perPage));
    }

    /** Get a single inventory unit by ID, including how many items reference it. */
    public function show(Unit $unit): UnitResource
    {
        $unit->items_count = $unit->itemsAsUnit1()->count()
            + $unit->itemsAsUnit2()->count()
            + $unit->itemsAsUnit3()->count();

        return new UnitResource($unit);
    }

    /** Create a new inventory unit. */
    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = $this->unitService->create($request->validated(), $request->user());

        return (new UnitResource($unit))->response()->setStatusCode(201);
    }

    /** Update an existing inventory unit. */
    public function update(UpdateUnitRequest $request, Unit $unit): UnitResource
    {
        return new UnitResource($this->unitService->update($unit, $request->validated()));
    }

    /**
     * Delete an inventory unit.
     * Returns 422 if the unit is referenced by any items.
     */
    public function destroy(Unit $unit): JsonResponse
    {
        $this->unitService->delete($unit);

        return response()->json(null, 204);
    }
}
