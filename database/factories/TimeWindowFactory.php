<?php

namespace Database\Factories;

use App\Models\TimeWindow;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

class TimeWindowFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TimeWindow::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $isActive = $this->faker->boolean(75);
        $isHalf = $this->faker->boolean(25);
        $weekDay = Arr::random(range(0,6));
        $startHour = $this->faker->numberBetween(8,11);
        $endHour = $this->faker->numberBetween($startHour+2,23);
        if (! $isActive) {
            $startHour = $this->faker->numberBetween(12,14);
            $endHour = $startHour+ 1;
        }
        return [
            'is_available' => $isActive,
            'week_day' => $weekDay,
            'start_hour' => str_pad($startHour, 2, '0', STR_PAD_LEFT).($isHalf ? ':30' : ':00'),
            'end_hour' =>  str_pad($endHour, 2, '0', STR_PAD_LEFT).($isHalf ? ':30' : ':00'),
        ];
    }
}
