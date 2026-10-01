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

    /**
     * Every active store, in one request.
     *
     * WHY
     * ---
     * The weekly grid shows all 44 stores. With one store per request that screen
     * makes 44 calls, each of which ALSO makes a synchronous token check against
     * pizzasys before it touches the database — 88 round trips and 44
     * verifications, throttled further by the browser's ~6 connections per origin.
     * This is one call.
     *
     * The query never cared: CountsQueryService::query() has always taken an array
     * of store ids. Only the route shape restricted it to one.
     *
     * NO STORE PARAMETER, ON PURPOSE
     * ------------------------------
     * The caller does not name the stores; it gets all of them. That is not a hole
     * — it is what makes the authorization simple and correct.
     *
     * pizzasys has a mode for exactly this: `store_scope_mode: all_stores`, which
     * its own resolver documents as "user must have access to every active store,
     * then check global perms". So the rule is one line, and it answers the right
     * question: this endpoint is for people who see everything. The specialist
     * passes; a store manager gets 403 and uses the per-store route above, which
     * is the one built for them.
     *
     * The alternative — a `stores=` list the client sends — would mean pizzasys
     * checking 44 ids on every call, a cap to enforce, and a rule that has to be
     * told where to look. Returning what the caller is entitled to see removes all
     * of it.
     *
     * The single-store route stays exactly as it was.
     */
    public function bulk(Request $request): JsonResponse
    {
        // Both flags arrive as the spelled-out word in a query string, which
        // Laravel's `boolean` rule rejects. Same normalisation as index().
        foreach (['all_entries', 'include_missing'] as $flag) {
            if ($request->has($flag)) {
                $request->merge([
                    $flag => filter_var($request->input($flag), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                ]);
            }
        }

        $data = $request->validate([
            'date_from'       => ['required', 'date_format:Y-m-d'],
            'date_to'         => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'ultimatrix_ids'  => ['nullable', 'string'],
            'all_entries'     => ['nullable', 'boolean'],
            // The grid needs the gaps; a dashboard that only totals what exists
            // does not, and across every store that list is the bulk of the
            // response — 44 x 7 days x 3 items is 924 combinations.
            'include_missing' => ['nullable', 'boolean'],
        ]);

        abort_if(
            Carbon::parse($data['date_from'])->diffInDays(Carbon::parse($data['date_to'])) > self::MAX_DAYS,
            422,
            'Date range may not exceed ' . self::MAX_DAYS . ' days.'
        );

        // Active stores only. A closed branch has no plan to be measured against,
        // and leaving it in would put a permanently empty row in the grid and a
        // week of phantom gaps in meta.missing.
        $ids = Store::query()
            ->orderBy('store_number')
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        abort_if($ids === [], 404, 'No active stores.');

        $itemRefs = $this->csv($data['ultimatrix_ids'] ?? '', self::MAX_ITEMS);

        $result = $this->counts->query(
            storeIds:   $ids,
            dateFrom:   $data['date_from'],
            dateTo:     $data['date_to'],
            itemRefs:   $itemRefs,
            allEntries: (bool) ($data['all_entries'] ?? false),
        );

        if (array_key_exists('include_missing', $data) && ! $data['include_missing']) {
            $result['meta']['missing'] = null;
        }

        // How many stores the answer covers. Without it the client cannot tell
        // "every store was counted" from "the grid is showing fewer stores than
        // it thinks" — stores_with_data alone does not say what the denominator is.
        $result['meta']['stores_total'] = count($ids);

        return response()->json($result);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** @return array<int, string> */
    private function csv(string $value, int $limit): array
    {
        $parts = array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== '');

        return array_slice(array_values(array_unique($parts)), 0, $limit);
    }
}
