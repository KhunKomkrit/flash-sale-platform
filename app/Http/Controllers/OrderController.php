<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OrderDashboardService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
     public function __construct(
        private readonly OrderDashboardService $dashboardService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 50), 50);
        $page = max((int) $request->input('page', 1), 1);

        return response()->json(
            $this->dashboardService->getDashboard($perPage, $page)
        );
    }
}
