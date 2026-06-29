<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\UpdateEntryItemRequest;
use App\Http\Resources\Inventory\EntryItemEditResource;
use App\Models\Inventory\EntryItem;
use App\Services\Inventory\EntryService;
use Illuminate\Http\JsonResponse;

class EntryItemController extends Controller
{
    public function __construct(private readonly EntryService $entryService) {}

    /**
     * Edit an entry item's counts.
     * Requires a reason (min 5 chars). Every edit is logged — history is append-only and never deleted.
     * Accessible to `store_manager` and `inventory_specialist` roles.
     */
    public function update(UpdateEntryItemRequest $request, EntryItem $entryItem): JsonResponse
    {
        $updated = $this->entryService->editEntryItem(
            $entryItem,
            $request->only(['count_unit_1', 'count_unit_2', 'count_unit_3']),
            $request->input('reason'),
            $request->user(),
        );

        return response()->json([
            'data' => [
                'id'             => $updated->id,
                'count_unit_1'   => $updated->count_unit_1,
                'count_unit_2'   => $updated->count_unit_2,
                'count_unit_3'   => $updated->count_unit_3,
                'total_in_unit_1' => $updated->total_in_unit_1,
                'is_edited'      => $updated->is_edited,
                'edits'          => EntryItemEditResource::collection($updated->edits),
            ],
        ]);
    }
}
