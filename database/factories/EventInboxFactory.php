<?php

namespace Database\Factories;

use App\Models\EventInbox;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventInboxFactory extends Factory
{
    protected $model = EventInbox::class;

    public function definition(): array
    {
        $subject = fake()->randomElement([
            'auth.user.created',
            'auth.user.role.assigned',
            'auth.user.role.removed',
            'auth.store.created',
            'auth.store.updated',
        ]);

        return [
            'event_id'     => (string) Str::ulid(),
            'subject'      => $subject,
            'source'       => 'auth-service',
            'stream'       => 'AUTH_EVENTS',
            'consumer'     => 'inventory-consumer',
            'payload'      => ['subject' => $subject, 'data' => ['id' => fake()->numberBetween(1, 9999)]],
            'processed_at' => now(),
            'attempts'     => 1,
            'parked_at'    => null,
            'last_error'   => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'processed_at' => null,
            'attempts'     => 0,
        ]);
    }

    public function parked(): static
    {
        return $this->state(fn () => [
            'processed_at' => null,
            'attempts'     => 5,
            'parked_at'    => now(),
            'last_error'   => 'Handler threw exception after max retries',
        ]);
    }
}
