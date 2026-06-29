<?php

namespace App\Services\EventConsume\Handlers;

use App\Models\Store;
use App\Services\EventConsume\EventHandlerInterface;
use Illuminate\Support\Facades\DB;

class StoreCreatedHandler implements EventHandlerInterface
{
    public function handle(array $event): void
    {
        $storePayload = $this->extractStorePayload($event);

        $id = trim((string) data_get($storePayload, 'id', ''));
        if ($id === '') {
            throw new \Exception('StoreCreatedHandler: missing/invalid store.id');
        }

        $storeIdString = $this->extractStoreIdString($storePayload);
        $name          = trim((string) data_get($storePayload, 'name', ''));
        $isActive      = (bool) data_get($storePayload, 'is_active', true);

        DB::transaction(function () use ($id, $storeIdString, $name, $isActive) {
            Store::query()->updateOrCreate(
                ['id' => $id],
                [
                    'store_number' => $storeIdString,
                    'name'         => $name,
                    'is_active'    => $isActive,
                ]
            );
        });
    }

    private function extractStorePayload(array $event): array
    {
        $store = data_get($event, 'data.store');
        if (is_array($store))
            return $store;

        $store = data_get($event, 'store');
        if (is_array($store))
            return $store;

        $store = data_get($event, 'payload.store');
        if (is_array($store))
            return $store;

        throw new \Exception('StoreCreatedHandler: store payload not found in event');
    }

    private function extractStoreIdString(array $storePayload): string
    {
        // Prefer store_id (manual string), fallback to anything usable.
        $v = data_get($storePayload, 'store_id');

        if (is_string($v) && trim($v) !== '') {
            return trim($v);
        }

        // Some producers might call it "store" already:
        $v = data_get($storePayload, 'store');
        if (is_string($v) && trim($v) !== '') {
            return trim($v);
        }

        // Last resort: if only numeric id exists, store it as string so the record is not broken.
        $id = data_get($storePayload, 'id');
        if (is_scalar($id) && (string) $id !== '') {
            return (string) $id;
        }

        return 'UNKNOWN';
    }

}
