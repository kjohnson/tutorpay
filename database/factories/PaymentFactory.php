<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payday' => '2026-11-05',
            'total_hours' => fake()->randomFloat(2, 5, 40),
            'hourly_rate' => fake()->optional()->randomFloat(2, 20, 60),
            'note' => null,
        ];
    }
}
