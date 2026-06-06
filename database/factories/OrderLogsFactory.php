<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SaleEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderLogs>
 */
class OrderLogsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::query()->inRandomOrder()->first();
        $user = User::query()->inRandomOrder()->first();
        $saleEvent = SaleEvent::query()->inRandomOrder()->first();
        return [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'sale_event_id' => $saleEvent->id,
            'quantity' => 1,
            'unit_price' => $product->price,
            'status' => fake()->randomElement(['pending', 'paid', 'cancelled', 'failed']),
        ];
    }
}
