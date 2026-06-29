<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\LinkAlreadySubmittedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\PublicSubmitEntryRequest;
use App\Http\Resources\Inventory\PublicLinkResource;
use App\Models\Inventory\InventoryLink;
use App\Services\Inventory\EntryService;
use Illuminate\Http\JsonResponse;

class PublicInventoryController extends Controller
{
    public function __construct(private readonly EntryService $entryService) {}

    /**
     * Load the public inventory form for a given token.
     * Returns 404 if the token is invalid.
     * Returns 410 Gone if the link has already been submitted.
     * No authentication required.
     */
    public function show(string $token): JsonResponse|PublicLinkResource
    {
        $link = InventoryLink::where('token', $token)
            ->with(['store', 'items.unit1', 'items.unit2', 'items.unit3'])
            ->first();

        if (! $link) {
            return response()->json(['message' => 'Link not found.'], 404);
        }

        if ($link->isSubmitted()) {
            return response()->json(['message' => 'This link has already been submitted.'], 410);
        }

        return new PublicLinkResource($link);
    }

    /**
     * Submit inventory counts for a public link.
     * Returns 404 if token is invalid.
     * Returns 410 Gone if already submitted — the link expires immediately on first submission.
     * No authentication required.
     */
    public function submit(string $token, PublicSubmitEntryRequest $request): JsonResponse
    {
        $link = InventoryLink::where('token', $token)->first();

        if (! $link) {
            return response()->json(['message' => 'Link not found.'], 404);
        }

        if ($link->isSubmitted()) {
            return response()->json(['message' => 'This link has already been submitted.'], 410);
        }

        try {
            $entry = $this->entryService->createFromPublicSubmission($link, $request->input('items'));
        } catch (LinkAlreadySubmittedException) {
            return response()->json(['message' => 'This link has already been submitted.'], 410);
        }

        return response()->json([
            'data' => [
                'reference'    => $entry->reference,
                'submitted_at' => $entry->submitted_at?->toIso8601String(),
            ],
        ], 201);
    }
}
