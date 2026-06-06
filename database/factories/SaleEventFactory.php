<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleEvent>
 */
class SaleEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-30 days', '+7 days');
        $endsAt = (clone $startsAt)->modify('+' . fake()->numberBetween(1, 72) . ' hours');
        return [
            'name' => 'Flash Sale ' . fake()->words(2, true),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_active' => true,
        ];
    }
}
