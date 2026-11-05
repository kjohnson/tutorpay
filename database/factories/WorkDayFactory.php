<?php

namespace Database\Factories;

use App\Models\WorkDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkDay>
 */
class WorkDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('now', '+4 weeks')->format('Y-m-d'),
        ];
    }
}
