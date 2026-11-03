<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryLink;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * GET /api/inventory/calendar/feed?stores=03795-00001[,…]&from=YYYY-MM-DD&to=YYYY-MM-DD[&group=day]
 *
 * `group=day` (year view, all-stores grid): instead of `events`, `days` — how
 * many counts each store has on each day, by status — for up to 371 days.
 *
 * Inventory's part of the unified calendar (calendar-feed/v1): one
 * `inventory_count` event per link — the link is the requested count, so the
 * calendar can show the ones nobody did, which entries cannot.
 *
 * Who may see which stores is pizzasys's decision, not ours: the auth middleware
 * forwards the query string and the rule for this route reads `stores` from it
 * (store_match_policy "all"). We only refuse numbers we do not know, because
 * pizzasys silently drops those instead of refusing them.
 *
 * The link `token` is never returned: it is the whole credential of the public
 * counting form.
 */
class CalendarFeedController extends Controller
{
    private const MAX_STORES = 50;

    private const MAX_DAYS = 62;

    /** With group=day: a year — a 53-week fiscal year is 371 days. */
    private const MAX_DAYS_GROUPED = 371;

    /** The statuses a day counts; `info` and cancelled are not counted. */
    private const COUNTED = ['done' => 1, 'pending' => 1, 'overdue' => 1, 'missed' => 1, 'in_progress' => 1, 'scheduled' => 1];

    // Every date and "today" (for overdue) is UTC; the response says so in
    // stores[].timezone.
    private const TIMEZONE = 'UTC';

    // ❓ Dashboard page for one count.
    private const LINK = '/dashboard/inventory/links/';

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'stores' => ['required', 'string'],
            'from'   => ['required', 'date_format:Y-m-d'],
            'to'     => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group'  => ['nullable', 'in:day'],
        ]);
        $grouped = ($data['group'] ?? null) === 'day';
        $maxDays = $grouped ? self::MAX_DAYS_GROUPED : self::MAX_DAYS;

        $numbers = array_values(array_unique(array_filter(array_map('trim', explode(',', $data['stores'])))));
        if ($numbers === [] || count($numbers) > self::MAX_STORES) {
            throw ValidationException::withMessages(['stores' => 'Send 1 to ' . self::MAX_STORES . ' store numbers.']);
        }
        if (Carbon::parse($data['from'])->diffInDays(Carbon::parse($data['to'])) > $maxDays) {
            throw ValidationException::withMessages(['to' => "Date range may not exceed {$maxDays} days."]);
        }

        // id => store_number, active stores only.
        $stores = Store::query()->whereIn('store_number', $numbers)->where('is_active', true)->pluck('store_number', 'id');
        $unknown = array_values(array_diff($numbers, $stores->values()->all()));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['stores' => 'Unknown or inactive store: ' . implode(', ', $unknown) . '.']);
        }

        $today = Carbon::now(self::TIMEZONE)->toDateString();

        $events = InventoryLink::query()
            ->with('employee:id,first_name,last_name')
            ->with('entry:id,link_id,submitted_at')
            ->withCount('items')
            ->whereIn('store_id', $stores->keys())
            ->whereBetween('date', [$data['from'], $data['to']])
            ->orderBy('date')->orderBy('id')
            ->get()
            ->map(fn (InventoryLink $link) => $this->event($link, $stores[$link->store_id], $today))
            ->all();

        $body = $grouped
            ? ['group' => 'day', 'days' => $this->days($events)]
            : ['events' => $events];

        return response()->json([
            'contract'     => 'calendar-feed/v1',
            'source'       => 'inventory',
            'generated_at' => $this->utc(now()),
            'range'        => ['from' => $data['from'], 'to' => $data['to']],
            'stores'       => array_map(fn ($n) => ['number' => $n, 'timezone' => self::TIMEZONE], $numbers),
            ...$body,
            'meta'         => ['count' => count($body['days'] ?? $body['events']), 'truncated' => false],
        ]);
    }

    /**
     * group=day: per store and day, how many things there are in each status.
     * A summary event adds its own counts; `info`, cancelled and store-less
     * events are not counted; days with nothing are left out.
     *
     * @return list<array{store: string, date: string, counts: array<string, int>}>
     */
    private function days(array $events): array
    {
        $days = [];
        foreach ($events as $e) {
            $add = isset($e['counts'])
                ? array_intersect_key($e['counts'], self::COUNTED)
                : (isset(self::COUNTED[$e['status']]) ? [$e['status'] => 1] : []);
            if ($e['store'] === null || array_sum($add) === 0) {
                continue;
            }
            $day = &$days[$e['date'] . '|' . $e['store']];
            $day ??= ['store' => $e['store'], 'date' => $e['date'], 'counts' => []];
            foreach (array_filter($add) as $status => $n) {
                $day['counts'][$status] = ($day['counts'][$status] ?? 0) + $n;
            }
            unset($day);
        }
        ksort($days);

        foreach ($days as &$day) {
            $day['counts']['total'] = array_sum($day['counts']);
        }
        unset($day);

        return array_values($days);
    }

    /** @return array<string, mixed> */
    private function event(InventoryLink $link, string $store, string $today): array
    {
        $date = $link->date->toDateString();
        $name = $this->shortName($link);

        return [
            'id'         => 'inventory:inventory_count:' . $link->id,
            'type'       => 'inventory_count',
            'layer'      => 'inventory',
            'store'      => $store,
            'title'      => ucfirst($link->type) . ' count — ' . $name,
            'all_day'    => true,
            'date'       => $date,
            'date_end'   => null,
            'start'      => null,
            'end'        => null,
            'status'     => match (true) {
                $link->status === 'submitted' => 'done',
                $date >= $today               => 'pending',
                default                       => 'overdue',
            },
            'people'     => $link->employee_id ? [['kind' => 'employee', 'id' => (int) $link->employee_id, 'name' => $name]] : [],
            'link'       => self::LINK . $link->id,
            'updated_at' => $this->utc($link->updated_at),
            'extra'      => [
                'count_type'   => $link->type,
                'submitted_at' => $this->utc($link->entry?->submitted_at),
                'items_count'  => (int) $link->items_count,
            ],
        ];
    }

    /** "Ana Lopez" → "Ana L.": first name and the family initial, never more. */
    private function shortName(InventoryLink $link): string
    {
        // The employee row is replicated from Hiring and can be missing; the name
        // snapshot taken when the link was made is always there.
        $first = $link->employee?->first_name;
        $last = $link->employee?->last_name;

        if ($first === null) {
            $parts = preg_split('/\s+/', trim($link->user_name));
            $first = $parts[0] ?? '';
            $last = count($parts) > 1 ? end($parts) : '';
        }

        return trim($first . ($last ? ' ' . mb_strtoupper(mb_substr($last, 0, 1)) . '.' : ''));
    }

    private function utc($at): ?string
    {
        return $at ? Carbon::parse($at)->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }
}
