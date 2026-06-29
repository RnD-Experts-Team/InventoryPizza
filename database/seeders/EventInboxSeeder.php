<?php

namespace Database\Seeders;

use App\Models\EventInbox;
use Illuminate\Database\Seeder;

class EventInboxSeeder extends Seeder
{
    public function run(): void
    {
        EventInbox::factory(8)->create();   // processed events
        EventInbox::factory(2)->pending()->create();
        EventInbox::factory(1)->parked()->create();
    }
}
