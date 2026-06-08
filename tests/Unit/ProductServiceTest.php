<?php

namespace Tests\Unit;

use App\Repositories\ProductRepository;
use App\Services\ProductService;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_reduce_stock_succeeds_when_atomic_decrement_succeeds(): void
    {
        $repository = Mockery::mock(ProductRepository::class);
        $repository->shouldReceive('decrementStockAtomically')
            ->once()
            ->with(10, 2)
            ->andReturnTrue();

        $service = new ProductService($repository);

        $this->assertTrue($service->reduceStock(10, 2));
    }

    public function test_reduce_stock_returns_false_when_stock_is_insufficient(): void
    {
        $repository = Mockery::mock(ProductRepository::class);
        $repository->shouldReceive('decrementStockAtomically')
            ->once()
            ->with(10, 5)
            ->andReturnFalse();

        $service = new ProductService($repository);

        $this->assertFalse($service->reduceStock(10, 5));
    }

    public function test_reduce_stock_returns_false_when_concurrent_request_consumes_stock_first(): void
    {
        $repository = Mockery::mock(ProductRepository::class);
        $repository->shouldReceive('decrementStockAtomically')
            ->once()
            ->with(10, 1)
            ->andReturnFalse();

        $service = new ProductService($repository);

        $this->assertFalse($service->reduceStock(10, 1));
    }

    public function test_reduce_stock_rejects_non_positive_quantity(): void
    {
        $repository = Mockery::mock(ProductRepository::class);
        $repository->shouldNotReceive('decrementStockAtomically');

        $service = new ProductService($repository);

        $this->expectException(InvalidArgumentException::class);

        $service->reduceStock(10, 0);
    }
}
