<?php

namespace App\Services\EventConsume\Handlers;

trait EmployeeHandlerHelpers
{
    private function resolveStoreId(array $emp, array $event): string
    {
        $stores = data_get($emp, 'stores', []);
        if (is_array($stores) && count($stores) > 0) {
            $latest = $this->latestEntry($stores);
            $num    = data_get($latest, 'store_number');
            if (is_string($num) && trim($num) !== '') {
                return trim($num);
            }
        }

        $num = data_get($event, 'data.store_number') ?? data_get($event, 'store_number');
        if (is_string($num) && trim($num) !== '') {
            return trim($num);
        }

        throw new \Exception('EmployeeHandler: cannot resolve store_id from payload');
    }

    private function resolveActive(array $emp): bool
    {
        $histories = data_get($emp, 'status_histories', []);
        if (!is_array($histories) || count($histories) === 0) {
            return true;
        }
        $latest = $this->latestEntry($histories);
        $status = strtolower((string) data_get($latest, 'status', ''));
        return in_array($status, ['hired', 'rehired', 'oje'], true);
    }

    private function latestEntry(array $entries): array
    {
        usort($entries, function ($a, $b) {
            return $this->entryTimestamp($b) <=> $this->entryTimestamp($a);
        });
        return $entries[0] ?? [];
    }

    private function entryTimestamp(array $entry): string
    {
        return (string) (
            data_get($entry, 'effective_date') ??
            data_get($entry, 'created_at')     ??
            data_get($entry, 'updated_at')     ??
            ''
        );
    }
}
