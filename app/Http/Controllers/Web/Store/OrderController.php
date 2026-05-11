<?php

namespace App\Http\Controllers\Web\Store;

use App\Domain\Orders\CustomerOrderQueryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly CustomerOrderQueryService $orders,
    ) {}

    public function show(Request $request, string $order_number): Response
    {
        $order = $this->orders->findOwnedByUser($request->user(), $order_number);
        abort_if($order === null, 404);

        return Inertia::render('Store/OrderShow', [
            'order' => $this->orders->toOrderDetailPayload($order),
            'seo' => ['title' => 'Order '.$order->order_number],
        ]);
    }
}
