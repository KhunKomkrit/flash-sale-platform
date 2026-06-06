<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => strtoupper(Str::random(8)) . '-' . fake()->unique()->numberBetween(100000, 999999),
            'stock' => fake()->numberBetween(50, 5000),
            'price' => fake()->randomFloat(2, 50, 5000),
            'is_active' => true,
        ];
    }
}
