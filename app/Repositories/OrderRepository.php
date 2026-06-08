<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderRepository
{
    public function paginate(): LengthAwarePaginator
    {
        return Order::query()->paginate();
    }

    public function findOrFail(int|string $id): Order
    {
        return Order::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Order
    {
        return Order::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Order $order, array $data): Order
    {
        $order->update($data);

        return $order->refresh();
    }

    public function delete(Order $order): void
    {
        $order->delete();
    }

    public function paginateDashboard(int $perPage, int $page): LengthAwarePaginator
    {
        return Order::query()
            ->with([
                'saleEvent:id,name',
                'user:id,email',
                'product:id,name,price',
            ])
            ->select([
                'id',
                'sale_event_id',
                'user_id',
                'product_id',
                'status',
                'created_at',
            ])
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
