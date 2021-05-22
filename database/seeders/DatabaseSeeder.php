<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;
use App\Models\TimeWindow;
use Illuminate\Support\Arr;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        Event::factory(10)
            ->create();

        TimeWindow::factory(5)->create([
            'event_id' => Arr::random(range(1,10))
        ]);
    }
}
