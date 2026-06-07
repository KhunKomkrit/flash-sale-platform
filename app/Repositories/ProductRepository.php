<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    public function findActiveById(int $productId): ?Product
    {
        return Product::query()
            ->where('is_active', true)
            ->find($productId);
    }

    public function decrementStockAtomically(int $productId, int $quantity): bool
    {
        return Product::query()
            ->whereKey($productId)
            ->where('stock', '>=', $quantity)
            ->decrement('stock', $quantity) === 1;
    }

    public function getTopSellingProducts(int $limit = 10): Collection
    {
        return Product::query()
            ->select('products.*')
            ->join('orders', 'orders.product_id', '=', 'products.id')
            ->where('orders.status', 'paid')
            ->groupBy('products.id')
            ->orderByRaw('COUNT(orders.id) DESC')
            ->limit($limit)
            ->get();
    }

    public function searchByName(string $keyword, int $limit = 20): Collection
    {
        return Product::query()
            ->where('name', 'like', "%{$keyword}%")
            ->limit($limit)
            ->get();
    }

    public function getActiveProducts(int $limit = 20): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->limit($limit)
            ->get();
    }

    public function searchActiveByName(string $keyword, int $limit = 20): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->where('name', 'like', "%{$keyword}%")
            ->limit($limit)
            ->get();
    }
}
