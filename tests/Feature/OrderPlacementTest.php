<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\SaleEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderPlacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_place_an_order(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 3, 'price' => 199.99]);
        $saleEvent = $this->activeSaleEvent();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'product_id' => $product->id,
            'sale_event_id' => $saleEvent->id,
            'quantity' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.sale_event_id', $saleEvent->id)
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'sale_event_id' => $saleEvent->id,
            'quantity' => 2,
            'status' => 'pending',
        ]);
        $this->assertSame(1, $product->refresh()->stock);
    }

    public function test_order_placement_fails_when_product_is_out_of_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 0]);
        $saleEvent = $this->activeSaleEvent();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'product_id' => $product->id,
            'sale_event_id' => $saleEvent->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Product is out of stock.');

        $this->assertDatabaseMissing('orders', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'sale_event_id' => $saleEvent->id,
        ]);
        $this->assertSame(0, $product->refresh()->stock);
    }

    public function test_order_placement_requires_authentication(): void
    {
        $product = Product::factory()->create(['stock' => 1]);
        $saleEvent = $this->activeSaleEvent();

        $response = $this->postJson('/api/orders', [
            'product_id' => $product->id,
            'sale_event_id' => $saleEvent->id,
            'quantity' => 1,
        ]);

        $response->assertUnauthorized();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_user_cannot_place_duplicate_order_in_same_sale_event(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 2]);
        $saleEvent = $this->activeSaleEvent();

        Order::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'sale_event_id' => $saleEvent->id,
            'quantity' => 1,
            'unit_price' => $product->price,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'product_id' => $product->id,
            'sale_event_id' => $saleEvent->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Duplicate order for this sale event.');

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, $product->refresh()->stock);
    }

    private function activeSaleEvent(): SaleEvent
    {
        return SaleEvent::factory()->create([
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'is_active' => true,
        ]);
    }
}
