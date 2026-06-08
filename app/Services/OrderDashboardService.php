<?php

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;

class OrderDashboardService
{
     public function __construct(
        private readonly OrderRepository $orders
    ) {}

    public function getDashboard(int $perPage, int $page): array
    {
        $cacheKey = "orders:dashboard:page:{$page}:per_page:{$perPage}";

        return Cache::tags(['orders-dashboard'])->remember($cacheKey, 60, function () use ($perPage, $page) {
            $orders = $this->orders->paginateDashboard($perPage, $page);

            return [
                'data' => $orders->getCollection()->map(function (Order $order) {
                    return [
                        'event' => $order->saleEvent?->name,
                        'user' => $order->user?->email,
                        'product' => $order->product?->name,
                        'price' => $order->product?->price,
                        'status' => $order->status,
                    ];
                }),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'last_page' => $orders->lastPage(),
                ],
            ];
        });
    }

    public function clearDashboardCache(): void
    {
        Cache::tags(['orders-dashboard'])->flush();
    }
}
