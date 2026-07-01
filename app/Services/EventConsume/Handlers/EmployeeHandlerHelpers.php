<?php

namespace App\Services\EventConsume\Handlers;

trait EmployeeHandlerHelpers
{
    private function resolveStoreId(array $emp, array $event): int
    {
        // The value written to employees.store_id MUST equal stores.id (now an integer).
        $raw = null;

        $stores = data_get($emp, 'stores', []);
        if (is_array($stores) && count($stores) > 0) {
            $latest = $this->latestEntry($stores);
            $raw    = data_get($latest, 'store_number');
        }

        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            $raw = data_get($event, 'data.store_number') ?? data_get($event, 'store_number');
        }

        $storeId = (int) $raw;
        if ($storeId <= 0) {
            throw new \Exception('EmployeeHandler: cannot resolve numeric store_id from payload');
        }

        return $storeId;
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
