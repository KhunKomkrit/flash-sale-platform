<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OrderDashboardService;
use App\Services\OrderPlacementService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class OrderController extends Controller
{
     public function __construct(
        private readonly OrderDashboardService $dashboardService,
        private readonly OrderPlacementService $orderPlacementService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 50), 50);
        $page = max((int) $request->input('page', 1), 1);

        return response()->json(
            $this->dashboardService->getDashboard($perPage, $page)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'sale_event_id' => ['required', 'integer', 'exists:sales_events,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ]);

        try {
            $order = $this->orderPlacementService->placeOrder(
                $request->user()->id,
                $data['product_id'],
                $data['sale_event_id'],
                $data['quantity'] ?? 1
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => [
                'id' => $order->id,
                'product_id' => $order->product_id,
                'sale_event_id' => $order->sale_event_id,
                'quantity' => $order->quantity,
                'unit_price' => $order->unit_price,
                'status' => $order->status,
            ],
        ], 201);
    }
}
