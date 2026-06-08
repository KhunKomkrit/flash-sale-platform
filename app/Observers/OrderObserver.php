<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\OrderDashboardService;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        $this->clearDashboardCache();
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        $this->clearDashboardCache();
    }

    private function clearDashboardCache(): void
    {
        app(OrderDashboardService::class)->clearDashboardCache();
    }
}
