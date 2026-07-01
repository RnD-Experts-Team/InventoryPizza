<?php

namespace App\Services\EventConsume\Handlers;

use App\Models\Store;
use App\Services\EventConsume\EventHandlerInterface;
use Illuminate\Support\Facades\DB;

class StoreUpdatedHandler implements EventHandlerInterface
{
    public function handle(array $event): void
    {
        $id = (int) (
            data_get($event, 'data.store_id') ?:
            data_get($event, 'store_id') ?:
            data_get($event, 'data.store.id') ?:
            data_get($event, 'store.id') ?? 0
        );

        if ($id <= 0) {
            throw new \Exception('StoreUpdatedHandler: missing/invalid store id');
        }

        $changed = data_get($event, 'data.changed_fields', []);
        if (!is_array($changed)) {
            $changed = [];
        }

        $metadataTo = data_get($changed, 'metadata.to');
        $isActiveTo = data_get($changed, 'is_active.to');

        DB::transaction(function () use ($id, $metadataTo, $isActiveTo, $changed) {
            /** @var Store $store */
            $store = Store::query()->find($id);

            if (!$store) {
                $store = new Store();
                $store->id = $id;
            }

            $update = [];

            if ($isActiveTo !== null) {
                $update['is_active'] = (bool) $isActiveTo;
            }

            $nameTo = data_get($metadataTo, 'name') ?? data_get($changed, 'name.to');
            if ($nameTo !== null) {
                $update['name'] = trim((string) $nameTo);
            }

            if (!empty($update)) {
                if (!$store->exists) {
                    $store->fill($update);
                    $store->save();
                } else {
                    $store->update($update);
                }
            }
        });
    }

}
