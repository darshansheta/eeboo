<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

class EventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Event::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $maxParticipation = $this->faker->numberBetween(50,100);
        $maxSlot = $this->faker->numberBetween(1,5);
        $maxSlotPerBooking = $this->faker->numberBetween(1,$maxSlot);
        $duration = Arr::random([10,20,30,60]);
        $preparation = Arr::random([10,15,20]);
        $advanceBookingDays = Arr::random([5,10,15]);
        return [
            'name' => $this->faker->name(),
            'max_participation' => $maxParticipation,
            'max_slot' => $maxSlot,
            'max_slot_per_booking' => $maxSlotPerBooking,
            'duration' => $duration,
            'preparation' => $preparation,
            'advance_booking_days' => $advanceBookingDays,
            'start_date' => null,
            'end_date' => null,
        ];
    }
}
