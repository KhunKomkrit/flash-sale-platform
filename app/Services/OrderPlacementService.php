<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\SaleEvent;
use App\Repositories\ProductRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderPlacementService
{
    public function __construct(
        private readonly ProductRepository $products
    ) {}

    public function placeOrder(int $userId, int $productId, int $saleEventId, int $quantity = 1): Order
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($userId, $productId, $saleEventId, $quantity) {
            $saleEvent = SaleEvent::query()
                ->whereKey($saleEventId)
                ->where('is_active', true)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->first();

            if (! $saleEvent) {
                throw new RuntimeException('Sale event is not active.');
            }

            $product = Product::query()
                ->whereKey($productId)
                ->where('is_active', true)
                ->first();

            if (! $product) {
                throw new RuntimeException('Product is not available.');
            }

            if (! $this->products->decrementStockAtomically($productId, $quantity)) {
                throw new RuntimeException('Product is out of stock.');
            }

            try {
                return Order::query()->create([
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'sale_event_id' => $saleEventId,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'status' => 'pending',
                ]);
            } catch (QueryException $exception) {
                if ($this->isDuplicateOrderException($exception)) {
                    throw new RuntimeException('Duplicate order for this sale event.', previous: $exception);
                }

                throw $exception;
            }
        });
    }

    private function isDuplicateOrderException(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), 'uniq_orders_user_product_event')
            || str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }
}
