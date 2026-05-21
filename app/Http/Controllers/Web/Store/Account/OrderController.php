<?php

namespace App\Http\Controllers\Web\Store\Account;

use App\Domain\Orders\CustomerOrderQueryService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Store\Account\Concerns\BuildsAccountSeo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    use BuildsAccountSeo;

    public function __construct(
        private readonly CustomerOrderQueryService $orders,
    ) {}

    public function index(Request $request): Response
    {
        $perPage = min((int) $request->query('per_page', 15), 50);
        $paginator = $this->orders->paginateForUser($request->user(), $perPage);

        return Inertia::render('Store/Account/Orders/Index', [
            'seo' => $this->accountSeo('Order history', '/account/orders'),
            'orders' => $paginator->getCollection()
                ->map(fn ($o) => $this->orders->toOrderListPayload($o))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, string $order_number): Response
    {
        $order = $this->orders->findOwnedByUser($request->user(), $order_number);
        abort_if($order === null, 404);

        return Inertia::render('Store/Account/Orders/Show', [
            'order' => $this->orders->toOrderDetailPayload($order),
            'seo' => $this->accountSeo('Order '.$order->order_number, '/account/orders/'.$order->order_number),
        ]);
    }
}
