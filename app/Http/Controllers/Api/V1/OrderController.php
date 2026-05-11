<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Orders\CustomerOrderQueryService;
use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly CustomerOrderQueryService $orders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 50);
        $paginator = $this->orders->paginateForUser($request->user(), $perPage);

        $items = $paginator->getCollection()->map(fn ($o) => [
            'order_number' => $o->order_number,
            'status' => $o->status->value,
            'payment_status' => $o->payment_status->value,
            'grand_total' => (float) $o->grand_total,
            'placed_at' => $o->created_at?->toIso8601String(),
        ])->values()->all();

        return ApiResponder::ok($items, 200, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, string $order_number): JsonResponse
    {
        $order = $this->orders->findOwnedByUser($request->user(), $order_number);
        if ($order === null) {
            return ApiResponder::error('Order not found.', 404, code: 'order_not_found');
        }

        return ApiResponder::ok($this->orders->toOrderDetailPayload($order));
    }
}
