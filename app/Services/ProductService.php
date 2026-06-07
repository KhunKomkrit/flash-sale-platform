<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Collection;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $productRepository
    ) {}

    public function reduceStock(int $productId, int $quantity): bool
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return $this->productRepository->decrementStockAtomically($productId, $quantity);
    }

    public function getTopSellingProducts(int $limit = 10): Collection
    {
        return $this->productRepository->getTopSellingProducts($limit);
    }

    public function searchProducts(string $keyword, int $limit = 20): Collection
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return $this->productRepository->getActiveProducts($limit);
        }

        return $this->productRepository->searchActiveByName($keyword, $limit);
    }
}
