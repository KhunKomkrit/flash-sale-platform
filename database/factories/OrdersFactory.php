<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrdersFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $order = Order::query()->inRandomOrder()->first();
        return [
            'order_id' => $order->id,
            'user_id' => $order->user_id ?? User::query()->inRandomOrder()->value('id'),
            'action' => fake()->randomElement(['created', 'paid', 'cancelled', 'failed']),
            'payload' => [
                'ip' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
            ],
        ];
    }
}
