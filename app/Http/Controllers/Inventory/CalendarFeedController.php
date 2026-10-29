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
 * GET /api/inventory/calendar/feed?stores=03795-00001[,…]&from=YYYY-MM-DD&to=YYYY-MM-DD
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
        ]);

        $numbers = array_values(array_unique(array_filter(array_map('trim', explode(',', $data['stores'])))));
        if ($numbers === [] || count($numbers) > self::MAX_STORES) {
            throw ValidationException::withMessages(['stores' => 'Send 1 to ' . self::MAX_STORES . ' store numbers.']);
        }
        if (Carbon::parse($data['from'])->diffInDays(Carbon::parse($data['to'])) > self::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'Date range may not exceed ' . self::MAX_DAYS . ' days.']);
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

        return response()->json([
            'contract'     => 'calendar-feed/v1',
            'source'       => 'inventory',
            'generated_at' => $this->utc(now()),
            'range'        => ['from' => $data['from'], 'to' => $data['to']],
            'stores'       => array_map(fn ($n) => ['number' => $n, 'timezone' => self::TIMEZONE], $numbers),
            'events'       => $events,
            'meta'         => ['count' => count($events), 'truncated' => false],
        ]);
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
