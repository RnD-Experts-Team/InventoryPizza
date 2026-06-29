<?php

namespace App\Services\EventConsume;

use Exception;

class EventRouter
{
    private array $map;

    public function __construct()
    {
        $devMode       = (bool) config('nats.dev_mode');
        $prefix        = $devMode ? 'auth.testing.v1'   : 'auth.v1';
        $hiringPrefix  = $devMode ? 'hiring.testing.v1' : 'hiring.v1';

        $this->map = [
            // User lifecycle
            "{$prefix}.user.created"       => \App\Services\EventConsume\Handlers\UserCreatedHandler::class,
            "{$prefix}.user.updated"       => \App\Services\EventConsume\Handlers\UserUpdatedHandler::class,
            "{$prefix}.user.deleted"       => \App\Services\EventConsume\Handlers\UserDeletedHandler::class,

            // Store lifecycle
            "{$prefix}.store.created"      => \App\Services\EventConsume\Handlers\StoreCreatedHandler::class,
            "{$prefix}.store.updated"      => \App\Services\EventConsume\Handlers\StoreUpdatedHandler::class,
            "{$prefix}.store.deleted"      => \App\Services\EventConsume\Handlers\StoreDeletedHandler::class,

            // Employee lifecycle (from Hiring system)
            "{$hiringPrefix}.employee.created" => \App\Services\EventConsume\Handlers\EmployeeCreatedHandler::class,
            "{$hiringPrefix}.employee.updated" => \App\Services\EventConsume\Handlers\EmployeeUpdatedHandler::class,
            "{$hiringPrefix}.employee.deleted" => \App\Services\EventConsume\Handlers\EmployeeDeletedHandler::class,
        ];
    }

    public function resolve(string $subject): string
    {
        return $this->map[$subject]
            ?? throw new Exception("No handler registered for subject '{$subject}'.");
    }

    public function getMap(): array
    {
        return $this->map;
    }
}
