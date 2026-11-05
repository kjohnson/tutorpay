<?php

namespace Database\Factories;

use App\Models\ClosedPayPeriod;
use App\Support\PayPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClosedPayPeriod>
 */
class ClosedPayPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'start_date' => '2026-10-22',
            'end_date' => '2026-11-04',
            'total_hours' => fake()->randomFloat(2, 5, 40),
            'hourly_rate' => fake()->optional()->randomFloat(2, 20, 60),
            'closed_at' => now(),
        ];
    }

    /**
     * Close the pay period that starts on the given date (Y-m-d).
     */
    public function startingOn(string $startDate): static
    {
        $period = new PayPeriod(CarbonImmutable::parse($startDate));

        return $this->state(fn (): array => [
            'start_date' => $period->start->toDateString(),
            'end_date' => $period->end->toDateString(),
        ]);
    }
}
