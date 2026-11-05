<?php

namespace Database\Factories;

use App\Models\ScheduleBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleBlock>
 */
class ScheduleBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startHour = fake()->numberBetween(8, 18);

        return [
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => sprintf('%02d:00:00', $startHour),
            'end_time' => sprintf('%02d:00:00', $startHour + fake()->numberBetween(1, 3)),
            'label' => fake()->optional()->firstName(),
        ];
    }

    /**
     * Place the block on a given day between the given times (HH:MM).
     */
    public function on(int $dayOfWeek, string $startTime, string $endTime): static
    {
        return $this->state(fn (): array => [
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime.':00',
            'end_time' => $endTime.':00',
        ]);
    }
}
