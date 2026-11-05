<?php

namespace Database\Factories;

use App\Models\WorkBlock;
use App\Models\WorkDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkBlock>
 */
class WorkBlockFactory extends Factory
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
            'work_day_id' => WorkDay::factory(),
            'start_time' => sprintf('%02d:00:00', $startHour),
            'end_time' => sprintf('%02d:00:00', $startHour + fake()->numberBetween(1, 3)),
            'label' => fake()->optional()->firstName(),
        ];
    }

    /**
     * Set the block's times (HH:MM).
     */
    public function between(string $startTime, string $endTime): static
    {
        return $this->state(fn (): array => [
            'start_time' => $startTime.':00',
            'end_time' => $endTime.':00',
        ]);
    }
}
