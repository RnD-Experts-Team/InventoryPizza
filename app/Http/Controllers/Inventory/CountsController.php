<?php

namespace App\Http\Controllers\Inventory;

use App\Models\Store;
use App\Http\Controllers\Controller;
use App\Services\Inventory\CountsQueryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Counted quantities for one store: a date range x selected items, in one query.
 *
 * Built for the Dough & Sauce module in AuditApp, whose browser calls this
 * directly. See CountsQueryService for why the existing entry endpoints could not
 * serve it.
 *
 * The store lives in the PATH as {store_id}. It could have been a comma list in
 * the query — pizzasys can read one, its auth_rules have a `query` source and an
 * `all` match policy — but every store-scoped route in this service puts it in the
 * path, and that is what the rules here are written against. A list would have
 * needed its own rule, and a rule that is never written is a route that nobody can
 * call: pizzasys denies when no rule matches (`allow_if_no_rule` is false). Being
 * consistent with the convention costs one request per store and removes that
 * whole class of mistake.
 */
class CountsController extends Controller
{
    /** A range wide enough for a month's review, narrow enough to stay cheap. */
    private const MAX_DAYS = 31;

    private const MAX_ITEMS = 20;

    public function __construct(private readonly CountsQueryService $counts) {}

    public function index(Request $request, string $store_id): JsonResponse
    {
        // ?all_entries=true arrives as the string "true", which Laravel's
        // `boolean` rule rejects — it takes true/false/1/0/"1"/"0" but not the
        // spelled-out words. A query string cannot carry a real boolean, and the
        // caller is a browser, so normalise here rather than make every client
        // send =1.
        if ($request->has('all_entries')) {
            $request->merge([
                'all_entries' => filter_var(
                    $request->input('all_entries'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }

        $data = $request->validate([
            'date_from'      => ['required', 'date_format:Y-m-d'],
            'date_to'        => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'ultimatrix_ids' => ['nullable', 'string'],
            'all_entries'    => ['nullable', 'boolean'],
        ]);

        // {store_id} carries the store_number the frontend knows; resolve it to the
        // internal id, exactly as EntryController::indexByStore does.
        $realStoreId = Store::idFromNumber($store_id);
        abort_if($realStoreId === null, 404, 'Store not found.');

        abort_if(
            Carbon::parse($data['date_from'])->diffInDays(Carbon::parse($data['date_to'])) > self::MAX_DAYS,
            422,
            'Date range may not exceed ' . self::MAX_DAYS . ' days.'
        );

        $itemRefs = $this->csv($data['ultimatrix_ids'] ?? '', self::MAX_ITEMS);

        return response()->json($this->counts->query(
            storeIds:   [$realStoreId],
            dateFrom:   $data['date_from'],
            dateTo:     $data['date_to'],
            itemRefs:   $itemRefs,
            allEntries: (bool) ($data['all_entries'] ?? false),
        ));
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** @return array<int, string> */
    private function csv(string $value, int $limit): array
    {
        $parts = array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== '');

        return array_slice(array_values(array_unique($parts)), 0, $limit);
    }
}
